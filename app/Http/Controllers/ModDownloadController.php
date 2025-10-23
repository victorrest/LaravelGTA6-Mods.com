<?php

namespace App\Http\Controllers;

use App\Models\Mod;
use App\Models\ModDownloadToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ModDownloadController extends Controller
{
    public function store(Request $request, Mod $mod): RedirectResponse
    {
        abort_unless($mod->status === Mod::STATUS_PUBLISHED, Response::HTTP_NOT_FOUND);

        $user = $request->user();
        $isExternal = ! $mod->file_path;
        $expiresAt = now()->addSeconds(config('gta6.downloads.token_ttl', 300));

        if ($user) {
            ModDownloadToken::query()
                ->where('mod_id', $mod->getKey())
                ->where('user_id', $user->getKey())
                ->whereNull('used_at')
                ->delete();
        }

        $downloadToken = ModDownloadToken::create([
            'mod_id' => $mod->getKey(),
            'user_id' => $user?->getKey(),
            'token' => Str::uuid()->toString(),
            'is_external' => $isExternal,
            'external_url' => $isExternal ? $mod->download_url : null,
            'expires_at' => $expiresAt,
        ]);

        return redirect()->route('mods.download.waiting', $downloadToken);
    }

    public function show(ModDownloadToken $downloadToken)
    {
        $downloadToken->loadMissing('mod.author');

        $mod = $downloadToken->mod;

        if (! $mod || $mod->status !== Mod::STATUS_PUBLISHED || $downloadToken->hasExpired() || $downloadToken->wasUsed()) {
            if ($mod) {
                return redirect()->route('mods.show', $mod)->with('downloadError', 'The download session has expired. Please try again.');
            }

            return redirect()->route('mods.index')->with('downloadError', 'The download session has expired. Please try again.');
        }

        $countdownSeconds = (int) config('gta6.downloads.waiting_room_countdown', 5);
        $externalDomain = null;

        if ($downloadToken->is_external && $downloadToken->external_url) {
            $parsed = parse_url($downloadToken->external_url, PHP_URL_HOST);
            $externalDomain = $parsed ? preg_replace('/^www\./i', '', $parsed) : null;
        }

        $authorMods = Mod::query()
            ->published()
            ->where('user_id', $mod->user_id)
            ->whereKeyNot($mod->getKey())
            ->with('categories')
            ->limit(4)
            ->get();

        return view('mods.waiting-room', [
            'downloadToken' => $downloadToken,
            'mod' => $mod->loadMissing(['categories', 'galleryImages']),
            'authorMods' => $authorMods,
            'countdownSeconds' => max($countdownSeconds, 3),
            'externalDomain' => $externalDomain,
        ]);
    }

    public function complete(ModDownloadToken $downloadToken)
    {
        $downloadToken->loadMissing('mod');
        $mod = $downloadToken->mod;

        if (! $mod || $mod->status !== Mod::STATUS_PUBLISHED) {
            abort(Response::HTTP_NOT_FOUND);
        }

        if ($downloadToken->hasExpired()) {
            return redirect()->route('mods.show', $mod)->with('downloadError', 'The download session has expired. Please try again.');
        }

        if ($downloadToken->wasUsed()) {
            return redirect()->route('mods.show', $mod)->with('status', 'This download link was already used.');
        }

        $downloadToken->markUsed();
        $mod->increment('downloads');

        if ($downloadToken->is_external) {
            $target = $downloadToken->external_url ?: $mod->download_url;

            return $target ? redirect()->away($target) : redirect()->route('mods.show', $mod)->with('downloadError', 'Download link is not available.');
        }

        if ($mod->file_path && Storage::disk('public')->exists($mod->file_path)) {
            $extension = pathinfo($mod->file_path, PATHINFO_EXTENSION) ?: 'zip';
            $filename = Str::slug($mod->title ?: 'gta6-mod') . '.' . $extension;

            return Storage::disk('public')->download($mod->file_path, $filename);
        }

        return $mod->download_url
            ? redirect()->away($mod->download_url)
            : redirect()->route('mods.show', $mod)->with('downloadError', 'Download link is not available.');
    }
}

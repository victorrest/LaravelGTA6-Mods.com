<?php

namespace App\Http\Controllers;

use App\Models\Mod;
use App\Models\ModVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ModVersionController extends Controller
{
    /**
     * Submit a new version for an existing mod
     */
    public function store(Request $request, Mod $mod)
    {

        // Check permissions
        if (!Auth::check() || (Auth::id() !== $mod->user_id && !Auth::user()->is_admin)) {
            abort(403, 'Nincs jogosultságod ehhez a művelethez.');
        }

        $validated = $request->validate([
            'version_number' => 'required|string|max:50',
            'changelog' => 'nullable|string|max:5000',
            'download_url' => 'required_without:mod_file|url|nullable',
            'mod_file' => 'required_without:download_url|file|mimes:zip,rar,7z|max:102400', // 100MB max
        ]);

        // Check if version number already exists
        if (ModVersion::where('mod_id', $mod->id)->where('version_number', $validated['version_number'])->exists()) {
            return back()->withErrors(['version_number' => 'Ez a verziószám már létezik ehhez a modhoz.'])->withInput();
        }

        $filePath = null;
        $fileSize = null;
        $downloadUrl = $validated['download_url'] ?? null;

        // Handle file upload
        if ($request->hasFile('mod_file')) {
            $file = $request->file('mod_file');
            $filePath = $file->store('mods/versions/' . $mod->id, 'public');
            $fileSize = round($file->getSize() / 1048576, 2); // Convert to MB

            if (!$downloadUrl) {
                $downloadUrl = Storage::disk('public')->url($filePath);
            }
        }

        // Auto-approve for admins, otherwise pending
        $status = Auth::user()->is_admin ? 'approved' : 'pending';

        $version = ModVersion::create([
            'mod_id' => $mod->id,
            'submitted_by' => Auth::id(),
            'version_number' => $validated['version_number'],
            'changelog' => $validated['changelog'],
            'file_path' => $filePath,
            'download_url' => $downloadUrl,
            'file_size' => $fileSize,
            'status' => $status,
            'is_current' => false, // Will be set by admin on approval
            'approved_at' => $status === 'approved' ? now() : null,
            'approved_by' => $status === 'approved' ? Auth::id() : null,
        ]);

        // Redirect back to mod page with success message
        $primaryCategory = $mod->primary_category ?? $mod->categories->first();
        return redirect()->route('mods.show', [$primaryCategory, $mod])
            ->with('success', $status === 'approved'
                ? 'Új verzió sikeresen hozzáadva.'
                : 'Új verzió beküldve moderációra. Jóváhagyás után jelenik meg.');
    }

    /**
     * Show version submission form
     */
    public function create(Mod $mod)
    {
        $mod->load('categories');

        // Check permissions
        if (!Auth::check() || (Auth::id() !== $mod->user_id && !Auth::user()->is_admin)) {
            abort(403);
        }

        return view('mods.version-submit', [
            'mod' => $mod,
        ]);
    }
}

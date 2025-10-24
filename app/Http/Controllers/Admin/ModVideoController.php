<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModVideo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ModVideoController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString() ?: ModVideo::STATUS_PENDING;

        $videos = ModVideo::query()
            ->with(['mod', 'author'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.mods.videos.index', [
            'videos' => $videos,
            'activeStatus' => $status,
        ]);
    }

    public function approve(ModVideo $modVideo): RedirectResponse
    {
        $modVideo->update([
            'status' => ModVideo::STATUS_APPROVED,
            'approved_at' => now(),
        ]);

        return back()->with('status', 'A videó publikálva lett.');
    }

    public function reject(Request $request, ModVideo $modVideo): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $modVideo->update([
            'status' => ModVideo::STATUS_REJECTED,
            'payload' => array_merge($modVideo->payload ?? [], [
                'rejection_reason' => $data['reason'] ?? null,
            ]),
        ]);

        return back()->with('status', 'A videó elutasításra került.');
    }
}

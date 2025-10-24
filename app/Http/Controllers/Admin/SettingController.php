<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function index(): View
    {
        $settings = Setting::query()
            ->orderBy('key')
            ->get()
            ->keyBy('key');

        return view('admin.settings.index', [
            'settings' => $settings,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'youtube_api_key' => ['nullable', 'string', 'max:255'],
        ]);

        if ($data['youtube_api_key'] ?? null) {
            Setting::set('youtube.api_key', $data['youtube_api_key'], 'YouTube API kulcs');
        }

        return back()->with('status', 'Beállítások frissítve.');
    }
}

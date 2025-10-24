<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = [
            'youtube_api_key' => Setting::get('youtube_api_key', ''),
        ];

        return view('admin.settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'youtube_api_key' => 'nullable|string|max:255',
        ]);

        Setting::set('youtube_api_key', $request->youtube_api_key ?? '', 'string');

        return redirect()->route('admin.settings.index')
            ->with('success', 'Beállítások sikeresen frissítve.');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ModVersionController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->get('status', 'pending');

        $query = ModVersion::with(['mod', 'submitter'])
            ->orderBy('created_at', 'desc');

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        $versions = $query->paginate(20);

        $statusCounts = [
            'all' => ModVersion::count(),
            'pending' => ModVersion::where('status', 'pending')->count(),
            'approved' => ModVersion::where('status', 'approved')->count(),
            'rejected' => ModVersion::where('status', 'rejected')->count(),
        ];

        return view('admin.versions.index', compact('versions', 'statusCounts', 'status'));
    }

    public function approve($id)
    {
        $version = ModVersion::findOrFail($id);

        DB::transaction(function () use ($version) {
            // Update version status
            $version->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => auth()->id(),
            ]);

            // Option to mark as current version (can be done separately)
            // $this->setAsCurrent($version);
        });

        return redirect()->back()->with('success', 'Verzió jóváhagyva.');
    }

    public function reject($id)
    {
        $version = ModVersion::findOrFail($id);

        $version->update([
            'status' => 'rejected',
            'rejected_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Verzió elutasítva.');
    }

    public function setAsCurrent($id)
    {
        $version = ModVersion::findOrFail($id);

        DB::transaction(function () use ($version) {
            // Unset all other versions as current for this mod
            ModVersion::where('mod_id', $version->mod_id)
                ->where('id', '!=', $version->id)
                ->update(['is_current' => false]);

            // Set this version as current
            $version->update([
                'is_current' => true,
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => auth()->id(),
            ]);

            // Update the mod's version field
            $version->mod->update([
                'version' => $version->version_number,
            ]);
        });

        return redirect()->back()->with('success', 'Verzió aktuálissá téve.');
    }

    public function destroy($id)
    {
        $version = ModVersion::findOrFail($id);

        // Delete file if exists
        if ($version->file_path && \Storage::disk('public')->exists($version->file_path)) {
            \Storage::disk('public')->delete($version->file_path);
        }

        $version->delete();

        return redirect()->back()->with('success', 'Verzió törölve.');
    }
}

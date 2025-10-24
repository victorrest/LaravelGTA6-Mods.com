<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModVideo;
use App\Models\ModVideoReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VideoController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->get('status', 'pending');

        $query = ModVideo::with(['mod', 'submitter', 'reports.user'])
            ->orderBy('created_at', 'desc');

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        $videos = $query->paginate(20);

        $statusCounts = [
            'all' => ModVideo::count(),
            'pending' => ModVideo::where('status', 'pending')->count(),
            'approved' => ModVideo::where('status', 'approved')->count(),
            'rejected' => ModVideo::where('status', 'rejected')->count(),
            'reported' => ModVideo::where('status', 'reported')->count(),
        ];

        return view('admin.videos.index', compact('videos', 'statusCounts', 'status'));
    }

    public function approve($id)
    {
        $video = ModVideo::findOrFail($id);

        $video->update([
            'status' => 'approved',
            'moderated_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Videó jóváhagyva.');
    }

    public function reject($id)
    {
        $video = ModVideo::findOrFail($id);

        $video->update([
            'status' => 'rejected',
            'moderated_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Videó elutasítva.');
    }

    public function destroy($id)
    {
        $video = ModVideo::findOrFail($id);
        $video->delete();

        return redirect()->back()->with('success', 'Videó törölve.');
    }

    public function clearReports($id)
    {
        $video = ModVideo::findOrFail($id);

        DB::transaction(function () use ($video) {
            ModVideoReport::where('video_id', $video->id)->delete();
            $video->update([
                'report_count' => 0,
                'status' => 'approved',
            ]);
        });

        return redirect()->back()->with('success', 'Jelentések törölve, videó jóváhagyva.');
    }
}

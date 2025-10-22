<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ForumThread;
use App\Models\Mod;
use App\Models\ModComment;
use App\Models\NewsArticle;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_mods' => Mod::count(),
            'pending_mods' => Mod::pending()->count(),
            'published_mods' => Mod::published()->count(),
            'total_users' => User::count(),
            'total_comments' => ModComment::count(),
            'forum_threads' => ForumThread::count(),
            'news_articles' => NewsArticle::count(),
        ];

        $recentMods = Mod::query()
            ->latest('created_at')
            ->with('author')
            ->limit(5)
            ->get();

        $recentUsers = User::query()
            ->latest('created_at')
            ->limit(5)
            ->get();

        $recentThreads = ForumThread::query()
            ->latest('created_at')
            ->with('author')
            ->limit(5)
            ->get();

        return view('admin.dashboard', [
            'stats' => $stats,
            'recentMods' => $recentMods,
            'recentUsers' => $recentUsers,
            'recentThreads' => $recentThreads,
        ]);
    }
}

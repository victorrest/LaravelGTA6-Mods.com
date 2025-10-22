<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ForumPost;
use App\Models\ForumThread;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ForumController extends Controller
{
    public function index(Request $request)
    {
        $query = ForumThread::query()->with('author')->withCount('posts');

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhereHas('author', fn ($authorQuery) => $authorQuery->where('name', 'like', "%{$search}%"));
            });
        }

        $threads = $query->latest('last_posted_at')->paginate(20)->withQueryString();

        return view('admin.forum.index', [
            'threads' => $threads,
        ]);
    }

    public function edit(ForumThread $forumThread)
    {
        $forumThread->load('author');
        $posts = $forumThread->posts()->with('author')->latest()->paginate(10);

        return view('admin.forum.edit', [
            'thread' => $forumThread,
            'posts' => $posts,
        ]);
    }

    public function update(Request $request, ForumThread $forumThread): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'flair' => ['nullable', 'string', 'max:80'],
            'pinned' => ['sometimes', 'boolean'],
            'locked' => ['sometimes', 'boolean'],
        ]);

        $forumThread->update([
            'title' => $data['title'],
            'flair' => $data['flair'] ?? null,
            'pinned' => $request->boolean('pinned'),
            'locked' => $request->boolean('locked'),
        ]);

        return redirect()->route('admin.forum.edit', $forumThread)->with('status', 'Thread updated successfully.');
    }

    public function destroy(ForumThread $forumThread): RedirectResponse
    {
        $forumThread->posts()->delete();
        $forumThread->delete();

        return redirect()->route('admin.forum.index')->with('status', 'Thread deleted successfully.');
    }

    public function destroyPost(ForumThread $forumThread, ForumPost $post): RedirectResponse
    {
        abort_unless($post->forum_thread_id === $forumThread->id, 404);

        $post->delete();

        $forumThread->update([
            'replies_count' => $forumThread->posts()->count(),
            'last_posted_at' => $forumThread->posts()->latest('created_at')->value('created_at'),
        ]);

        return redirect()->route('admin.forum.edit', $forumThread)->with('status', 'Post removed successfully.');
    }
}

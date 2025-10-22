<?php

namespace App\Http\Controllers;

use App\Http\Requests\ThreadReplyRequest;
use App\Http\Requests\ThreadStoreRequest;
use App\Models\ForumThread;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ForumController extends Controller
{
    public function index()
    {
        return view('forum.index', [
            'threads' => ForumThread::query()->with('author')->latestActivity()->paginate(15),
        ]);
    }

    public function show(ForumThread $thread)
    {
        $thread->load(['author']);

        return view('forum.show', [
            'thread' => $thread,
            'posts' => $thread->posts()->with('author')->orderBy('created_at')->get(),
        ]);
    }

    public function create()
    {
        return view('forum.create', [
            'flairs' => ['news', 'help', 'release', 'discussion'],
        ]);
    }

    public function store(ThreadStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $thread = ForumThread::create([
            'user_id' => Auth::id(),
            'title' => $data['title'],
            'slug' => $this->generateUniqueSlug($data['title']),
            'flair' => $data['flair'] ?: null,
            'body' => $data['body'],
            'last_posted_at' => now(),
        ]);

        $thread->posts()->create([
            'user_id' => Auth::id(),
            'body' => $data['body'],
        ]);

        $thread->update(['replies_count' => 1]);

        return redirect()->route('forum.show', $thread)->with('status', 'Thread created successfully.');
    }

    public function reply(ThreadReplyRequest $request, ForumThread $thread): RedirectResponse
    {
        abort_if($thread->locked, 403, 'Thread is locked.');

        $data = $request->validated();

        $thread->posts()->create([
            'user_id' => Auth::id(),
            'body' => $data['body'],
        ]);

        $thread->increment('replies_count');
        $thread->update(['last_posted_at' => now()]);

        return back()->with('status', 'Reply added successfully.');
    }

    private function generateUniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $counter = 1;

        while (ForumThread::where('slug', $slug)->exists()) {
            $slug = $base . '-' . ++$counter;
        }

        return $slug;
    }
}

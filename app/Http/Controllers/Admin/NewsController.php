<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsArticle;
use App\Rules\EditorJsContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class NewsController extends Controller
{
    public function index()
    {
        $articles = NewsArticle::query()->with('author')->latest('published_at')->paginate(15);

        return view('admin.news.index', [
            'articles' => $articles,
        ]);
    }

    public function create()
    {
        return view('admin.news.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:180', 'alpha_dash', 'unique:news_articles,slug'],
            'excerpt' => ['required', 'string', 'max:280'],
            'body' => ['required', 'string', new EditorJsContent(20)],
            'published_at' => ['nullable', 'date'],
        ]);

        $slug = $data['slug'] ?? Str::slug($data['title']);
        $slug = $this->ensureUniqueSlug($slug);

        NewsArticle::create([
            'user_id' => Auth::id(),
            'title' => $data['title'],
            'slug' => $slug,
            'excerpt' => $data['excerpt'],
            'body' => $data['body'],
            'published_at' => $data['published_at'] ?? now(),
        ]);

        cache()->forget('home:landing');

        return redirect()->route('admin.news.index')->with('status', 'News article published successfully.');
    }

    public function edit(NewsArticle $news)
    {
        return view('admin.news.edit', [
            'article' => $news,
        ]);
    }

    public function update(Request $request, NewsArticle $news): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'slug' => ['required', 'string', 'max:180', 'alpha_dash', Rule::unique('news_articles', 'slug')->ignore($news->id)],
            'excerpt' => ['required', 'string', 'max:280'],
            'body' => ['required', 'string', new EditorJsContent(20)],
            'published_at' => ['nullable', 'date'],
        ]);

        $news->update([
            'title' => $data['title'],
            'slug' => $data['slug'],
            'excerpt' => $data['excerpt'],
            'body' => $data['body'],
            'published_at' => $data['published_at'] ?? now(),
        ]);

        cache()->forget('home:landing');

        return redirect()->route('admin.news.edit', $news)->with('status', 'News article updated successfully.');
    }

    public function destroy(NewsArticle $news): RedirectResponse
    {
        $news->delete();

        cache()->forget('home:landing');

        return redirect()->route('admin.news.index')->with('status', 'News article removed successfully.');
    }

    private function ensureUniqueSlug(string $slug): string
    {
        $base = $slug;
        $counter = 1;

        while (NewsArticle::where('slug', $slug)->exists()) {
            $slug = $base . '-' . ++$counter;
        }

        return $slug;
    }
}

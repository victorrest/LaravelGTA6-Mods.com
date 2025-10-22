<?php

namespace App\Http\Controllers;

use App\Models\NewsArticle;

class NewsController extends Controller
{
    public function index()
    {
        return view('news.index', [
            'articles' => NewsArticle::query()->with('author')->orderByDesc('published_at')->paginate(10),
        ]);
    }

    public function show(NewsArticle $article)
    {
        return view('news.show', [
            'article' => $article->load('author'),
        ]);
    }
}

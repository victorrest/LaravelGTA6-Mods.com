<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModComment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function index(Request $request)
    {
        $query = ModComment::query()->with(['mod', 'author'])->latest();

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('body', 'like', "%{$search}%")
                    ->orWhereHas('author', fn ($authorQuery) => $authorQuery->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('mod', fn ($modQuery) => $modQuery->where('title', 'like', "%{$search}%"));
            });
        }

        $comments = $query->paginate(30)->withQueryString();

        return view('admin.comments.index', [
            'comments' => $comments,
        ]);
    }

    public function destroy(ModComment $comment): RedirectResponse
    {
        $comment->delete();

        return redirect()->route('admin.comments.index')->with('status', 'Comment removed successfully.');
    }
}

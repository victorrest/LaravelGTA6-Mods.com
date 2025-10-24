<?php

namespace App\Http\Controllers;

use App\Models\Mod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class ModCommentController extends Controller
{
    public function index(Request $request, Mod $mod): JsonResponse
    {
        abort_unless($mod->status === Mod::STATUS_PUBLISHED, 404);

        $perPage = 15;
        $page = max(1, (int) $request->query('page', 1));
        $order = Str::lower($request->query('order', 'best'));

        $query = $mod->comments()
            ->approved()
            ->whereNull('parent_id')
            ->with(['author', 'replies.author']);

        switch ($order) {
            case 'newest':
                $query->orderByDesc('created_at');
                break;
            case 'oldest':
                $query->orderBy('created_at');
                break;
            default:
                $query->orderByDesc('likes_count')->orderByDesc('created_at');
                break;
        }

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $html = view('mods.partials.comment-thread', [
            'mod' => $mod,
            'comments' => $paginator->getCollection(),
        ])->render();

        return response()->json([
            'success' => true,
            'html' => $html,
            'page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'order' => $order,
        ]);
    }
}

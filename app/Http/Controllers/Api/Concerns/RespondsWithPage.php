<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait RespondsWithPage
{
    protected function paged(Request $request, Builder $query, callable $map): JsonResponse
    {
        $perPage = min(50, max(1, (int) $request->integer('per_page', 20)));
        $page = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => $page->getCollection()->map($map)->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }
}
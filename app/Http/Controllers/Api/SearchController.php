<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Http\Responses\ApiResponse;
use App\Services\PostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(private readonly PostService $posts)
    {
    }

    /**
     * GET /api/search?q=keyword&limit=20
     *
     * Parameter bound LIKE search for now; Meilisearch takes over this endpoint
     * once the search infrastructure is in place.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $limit = (int) ($validated['limit'] ?? 20);
        $paginator = $this->posts->paginate(['q' => $validated['q']], $limit);

        return ApiResponse::paginated(
            PostResource::collection($paginator->items()),
            $paginator->total(),
            $paginator->currentPage(),
            $limit,
        );
    }
}

<?php

namespace Modules\Block\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Block\Repositories\BlockRepository;

/**
 * Unauthenticated, read-only content block API. Disabled blocks are a 404.
 */
class PublicBlockController extends Controller
{
    public function __construct(protected BlockRepository $blocks) {}

    public function show(Request $request): JsonResponse
    {
        $block = $this->blocks->getBlockContent((string) $request->route('slug'), app()->getLocale());

        if (! $block) {
            return response()->json(['success' => false, 'message' => trans('core::core.messages.route_not_found')], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'slug' => $block->slug,
                'title' => $block->title,
                'content' => $block->content,
            ],
        ]);
    }
}

<?php

namespace Modules\Pages\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Pages\Repositories\PagesRepository;
use Modules\Pages\Transformers\PageResource;

/**
 * Unauthenticated, read-only CMS page API. Disabled pages are a 404.
 */
class PublicPageController extends Controller
{
    public function __construct(protected PagesRepository $pages) {}

    public function show(Request $request): PageResource|JsonResponse
    {
        $page = $this->pages->findPublishedBySlug((string) $request->route('slug'));

        if (! $page) {
            return response()->json(['success' => false, 'message' => trans('pages::front_pages.messages.page_not_found')], 404);
        }

        return (new PageResource($page))->additional(['success' => true]);
    }
}

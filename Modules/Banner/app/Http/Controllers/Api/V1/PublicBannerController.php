<?php

namespace Modules\Banner\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Banner\Repositories\BannerGroupRepository;

/**
 * Unauthenticated, read-only banner API: the active banners of a group.
 */
class PublicBannerController extends Controller
{
    public function __construct(protected BannerGroupRepository $groups) {}

    public function group(Request $request): JsonResponse
    {
        $banners = collect($this->groups->getBannersByGroup((string) $request->route('code'), app()->getLocale()));

        return response()->json([
            'success' => true,
            'data' => $banners->sortBy('sort_order')->map(fn (array $banner) => [
                'id' => $banner['id'],
                'code' => $banner['code'],
                'title' => $banner['translations'][0]['title'] ?? null,
                'content' => $banner['translations'][0]['content'] ?? null,
                'image_url' => getImageUrl(['module' => 'banner', 'image' => $banner['image'] ?? null]),
                'url' => $banner['url'] ?? null,
                'is_featured' => (int) ($banner['is_featured'] ?? 0) === (int) config('core.yes'),
            ])->values(),
        ]);
    }
}

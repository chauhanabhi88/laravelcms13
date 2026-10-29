<?php

declare(strict_types=1);

namespace Modules\Storefront\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Api\ApiController;
use Modules\Storefront\Services\Home\HomePageBuilder;
use Modules\Storefront\Transformers\ProductCardResource;

/**
 * GET /api/v1/home — the active home sections with their content, for apps. The
 * "products_for_you" section points the app at /api/v1/products.
 */
class HomeController extends ApiController
{
    public function __construct(protected HomePageBuilder $builder) {}

    public function index(Request $request): JsonResponse
    {
        $sections = [];

        foreach ($this->builder->sections() as $section) {
            $data = $section['type'] === HomePageBuilder::LISTING_TYPE ? [] : $this->builder->data($section);

            if ($data === null) {
                continue;
            }

            $sections[] = [
                'id' => $section['id'],
                'type' => $section['type'],
                'title' => $section['title'],
                'subtitle' => $section['subtitle'],
                'data' => $this->present($section['type'], $data, $request),
            ];
        }

        return $this->ok($sections);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function present(string $type, array $data, Request $request): array
    {
        return match ($type) {
            'hero' => ['slides' => $data['slides']],
            'banner_row' => ['banners' => $data['banners']],
            'category_tiles' => ['tiles' => $data['tiles']],
            'collection' => [
                'slug' => $data['collection']->slug,
                'name' => $data['collection']->name,
                'url' => $data['url'],
                'products' => ProductCardResource::collection($data['cards'])->resolve($request),
            ],
            'html_block' => ['title' => $data['block']->title, 'content' => $data['block']->content],
            'blog_posts' => ['posts' => $data['posts']->map(fn ($post): array => ['id' => $post->id, 'title' => $post->title, 'slug' => $post->slug])->values()->all()],
            'products_for_you' => ['endpoint' => route('storefront_api.search', config('core.translation_api') ? ['locale' => app()->getLocale()] : [])],
            default => [],
        };
    }
}

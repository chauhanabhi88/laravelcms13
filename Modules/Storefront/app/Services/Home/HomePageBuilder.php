<?php

declare(strict_types=1);

namespace Modules\Storefront\Services\Home;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\Banner\Repositories\BannerGroupRepository;
use Modules\Block\Repositories\BlockRepository;
use Modules\Blog\Repositories\BlogPostRepository;
use Modules\Catalog\Services\CategoryTreeService;
use Modules\Storefront\Contracts\HomeSectionProvider;
use Modules\Storefront\Contracts\ProductSearchEngine;
use Modules\Storefront\Models\HomeSection;
use Modules\Storefront\Models\ProductCollection;
use Modules\Storefront\Services\CollectionQuery;
use Modules\Storefront\Services\Search\ListingQuery;
use Modules\Storefront\Support\StorefrontCache;
use Modules\Storefront\Support\StorefrontUrls;
use Nwidart\Modules\Facades\Module;

/**
 * The configurable home page (FR-DSC-001): active sections in sort order, each rendered to
 * an HTML fragment cached per locale for storefront.home_cache_ttl seconds. Saving a section
 * or a collection flushes the "home" cache bucket. "products_for_you" is never cached: it is
 * the request-driven listing (filters, sort, infinite scroll) rendered by the controller.
 *
 * @phpstan-type Section array{id: int, type: string, config: array<string, mixed>, title: string|null, subtitle: string|null}
 */
class HomePageBuilder
{
    public const LISTING_TYPE = 'products_for_you';

    /**
     * Container tag of HomeSectionProvider implementations (section types of other modules).
     */
    public const SECTION_PROVIDER_TAG = 'storefront.home_section_providers';

    public function __construct(
        protected StorefrontCache $cache,
        protected CategoryTreeService $tree,
        protected ProductSearchEngine $search,
        protected CollectionQuery $collections,
    ) {}

    /**
     * Active sections as plain arrays (cached).
     *
     * @return list<Section>
     */
    public function sections(): array
    {
        return $this->cache->remember('home', 'sections.'.app()->getLocale(), $this->ttl(), fn (): array => HomeSection::query()
            ->with('translations')
            ->active()
            ->whereIn('type', (array) config('storefront.home_section_types'))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (HomeSection $section): array => [
                'id' => $section->id,
                'type' => $section->type,
                'config' => (array) $section->config,
                'title' => $section->title,
                'subtitle' => $section->subtitle,
            ])
            ->all());
    }

    /**
     * The rendered fragment of a section ('' when it has nothing to show).
     *
     * @param  Section  $section
     */
    public function html(array $section): string
    {
        if ($section['type'] === self::LISTING_TYPE) {
            return '';
        }

        return $this->cache->remember('home', 'html.'.$section['id'].'.'.app()->getLocale(), $this->ttl(), function () use ($section): string {
            try {
                $provider = $this->provider($section['type']);
                $data = $provider !== null ? $provider->data($section['config']) : $this->data($section);
                $view = $provider !== null ? $provider->view() : 'storefront::frontend.sections.'.$section['type'];

                return $data === null ? '' : view($view, ['section' => $section] + $data)->render();
            } catch (\Throwable $e) {
                // A broken section (missing module, bad config) must never take the home page down.
                Log::error($e);

                return '';
            }
        });
    }

    /**
     * View data per section type; null hides the section.
     *
     * @param  Section  $section
     * @return array<string, mixed>|null
     */
    public function data(array $section): ?array
    {
        $config = $section['config'];

        return match ($section['type']) {
            'trust_strip' => [],
            'hero' => ($slides = $this->banners((string) ($config['banner_group_code'] ?? ''))) !== [] ? ['slides' => $slides] : null,
            'banner_row' => ($banners = array_slice($this->banners((string) ($config['banner_group_code'] ?? '')), 0, 3)) !== [] ? ['banners' => $banners] : null,
            'category_tiles' => ($tiles = $this->categoryTiles((array) ($config['category_ids'] ?? []))) !== [] ? ['tiles' => $tiles] : null,
            'collection' => $this->collection((int) ($config['collection_id'] ?? 0), (int) ($config['limit'] ?? config('storefront.home_defaults.collection_limit', 12))),
            'html_block' => ($block = $this->block((string) ($config['block_code'] ?? ''))) !== null ? ['block' => $block] : null,
            'blog_posts' => ($posts = $this->posts((int) ($config['limit'] ?? config('storefront.home_defaults.blog_posts_limit', 3))))->isNotEmpty() ? ['posts' => $posts] : null,
            default => null,
        };
    }

    /**
     * The module provider of a section type, if another module owns it.
     */
    protected function provider(string $type): ?HomeSectionProvider
    {
        foreach (app()->tagged(self::SECTION_PROVIDER_TAG) as $provider) {
            if ($provider instanceof HomeSectionProvider && $provider->type() === $type) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * @return list<array{image: string|null, title: string|null, content: string|null, url: string|null}>
     */
    protected function banners(string $code): array
    {
        if ($code === '' || ! Module::isEnabled('Banner')) {
            return [];
        }

        return collect(app(BannerGroupRepository::class)->getBannersByGroup($code, app()->getLocale()))
            ->filter(fn (array $banner): bool => (int) ($banner['status'] ?? config('core.enabled')) === (int) config('core.enabled'))
            ->sortBy('sort_order')
            ->map(fn (array $banner): array => [
                'image' => getImageUrl(['module' => 'banner', 'image' => $banner['image'] ?? null]),
                'title' => $banner['translations'][0]['title'] ?? null,
                'content' => $banner['translations'][0]['content'] ?? null,
                'url' => $banner['url'] ?? null,
            ])
            ->filter(fn (array $banner): bool => $banner['image'] !== null || $banner['title'] !== null)
            ->values()
            ->all();
    }

    /**
     * The configured categories in order, or the top-level menu categories.
     *
     * @param  list<int|string>  $categoryIds
     * @return list<array{name: string, url: string, image: string|null}>
     */
    protected function categoryTiles(array $categoryIds): array
    {
        $enabled = (int) config('core.enabled');
        $nodes = $this->tree->nodesById();
        $limit = (int) config('storefront.home_defaults.category_tiles_limit', 12);

        $picked = $categoryIds !== []
            ? array_filter(array_map(fn ($id): ?array => $nodes[(int) $id] ?? null, $categoryIds))
            : array_filter($nodes, fn (array $node): bool => $node['parent_id'] === null && $node['show_in_menu'] === (int) config('core.yes'));

        return array_values(array_map(fn (array $node): array => [
            'name' => $node['name'],
            'url' => StorefrontUrls::category($node['slug']),
            'image' => $node['image'] ? getImageUrl(['module' => config('catalog.upload.category'), 'image' => $node['image']]) : null,
        ], array_slice(array_values(array_filter($picked, fn (array $node): bool => $node['status'] === $enabled)), 0, $limit)));
    }

    /**
     * @return array{collection: ProductCollection, cards: list<array<string, mixed>>, url: string}|null
     */
    protected function collection(int $collectionId, int $limit): ?array
    {
        $collection = $collectionId > 0 ? ProductCollection::query()->with('translations')->enabled()->find($collectionId) : null;

        if ($collection === null) {
            return null;
        }

        $result = $this->search->run(new ListingQuery(...$this->collections->fixed($collection) + ['perPage' => max(1, min(40, $limit))]), false);

        return $result->isEmpty() ? null : [
            'collection' => $collection,
            'cards' => $result->cards,
            'url' => StorefrontUrls::collection($collection->slug),
        ];
    }

    protected function block(string $code): ?object
    {
        if ($code === '' || ! Module::isEnabled('Block')) {
            return null;
        }

        $block = app(BlockRepository::class)->getBlockContent($code, app()->getLocale());

        return $block && ($block->title || $block->content) ? $block : null;
    }

    /**
     * Featured articles, topped up with the latest ones.
     */
    protected function posts(int $limit): Collection
    {
        if (! Module::isEnabled('Blog') || $limit < 1) {
            return collect();
        }

        $posts = app(BlogPostRepository::class);
        $featured = $posts->getFeaturedPosts($limit);

        if ($featured->count() >= $limit) {
            return $featured;
        }

        $latest = collect($posts->getPublishedPosts([], $limit * 2)->items())
            ->reject(fn ($post): bool => $featured->contains('id', $post->id));

        return $featured->concat($latest)->take($limit)->values();
    }

    protected function ttl(): int
    {
        return (int) config('storefront.home_cache_ttl', 300);
    }
}

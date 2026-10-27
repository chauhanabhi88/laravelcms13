<?php

declare(strict_types=1);

namespace Modules\Theme\Support;

use Illuminate\Support\Facades\Route;
use Modules\Pages\Repositories\PagesRepository;
use Nwidart\Modules\Facades\Module;

/**
 * Resolves configured storefront links (named route and/or CMS page slug) to URLs.
 *
 * Bound as a scoped singleton: every CMS page the header and footer reference is
 * loaded in one query per request.
 */
class StorefrontLinks
{
    /**
     * @var array<string, array{url: string, title: string}>|null
     */
    protected ?array $pages = null;

    /**
     * @param  array{route?: string, module?: string, page?: string, label?: string}  $link
     * @return array{url: string, label: string}|null null when neither the route nor the page is available
     */
    public function resolve(array $link): ?array
    {
        $url = $this->routeUrl($link['route'] ?? null, $link['module'] ?? null);
        $page = $url ? null : $this->page($link['page'] ?? null);
        $url ??= $page['url'] ?? null;

        if (! $url) {
            return null;
        }

        return [
            'url' => $url,
            'label' => isset($link['label']) ? trans($link['label']) : ($page['title'] ?? ''),
        ];
    }

    /**
     * URL of a named front route, or null when it is not registered (yet) or its module is disabled.
     *
     * @param  array<int|string, mixed>  $parameters
     */
    public function routeUrl(?string $route, ?string $module = null, array $parameters = []): ?string
    {
        if (! $route || ! Route::has($route) || ($module && ! Module::isEnabled($module))) {
            return null;
        }

        return frontRoute($route, $parameters);
    }

    /**
     * @return array{url: string, title: string}|null
     */
    public function page(?string $slug): ?array
    {
        if (! $slug) {
            return null;
        }

        return $this->pages()[$slug] ?? null;
    }

    /**
     * @return array<string, array{url: string, title: string}>
     */
    protected function pages(): array
    {
        if ($this->pages !== null) {
            return $this->pages;
        }

        if (! Module::isEnabled('Pages') || ! Route::has('page')) {
            return $this->pages = [];
        }

        return $this->pages = app(PagesRepository::class)
            ->getPublishedBySlugs($this->configuredSlugs())
            ->mapWithKeys(fn ($page): array => [$page->slug => [
                'url' => frontRoute('page', [$page->slug]),
                'title' => (string) $page->title,
            ]])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function configuredSlugs(): array
    {
        return collect(config('theme.footer.columns', []))
            ->flatMap(fn (array $column): array => $column['links'] ?? [])
            ->push(config('theme.header.sell', []))
            ->pluck('page')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}

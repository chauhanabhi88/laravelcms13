<?php

namespace Modules\Theme\Composers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Modules\Block\Repositories\BlockRepository;
use Modules\Theme\Support\MegaMenu;
use Modules\Theme\Support\StorefrontLinks;
use Modules\Theme\Support\ThemeSettings;
use Nwidart\Modules\Facades\Module;

/**
 * Shared data for the public header and footer partials.
 */
class FrontendLayoutComposer
{
    public function __construct(
        protected BlockRepository $blocks,
        protected StorefrontLinks $links,
        protected MegaMenu $megaMenu,
    ) {}

    /**
     * The header and footer share one resolved payload, memoised on the request.
     */
    public function compose(View $view): void
    {
        $attributes = request()->attributes;

        if (! $attributes->has('theme.frontend_layout')) {
            $attributes->set('theme.frontend_layout', $this->resolve());
        }

        $view->with($attributes->get('theme.frontend_layout'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolve(): array
    {
        return [
            'siteName' => config('app.name'),
            'logoUrl' => ThemeSettings::logoUrl(),
            'logoDarkUrl' => ThemeSettings::logoDarkUrl(),
            'navItems' => $navItems = $this->navItems(),
            'currentCustomer' => Auth::guard('customer')->user(),
            'footerAbout' => $this->footerAbout(),
            'footerShop' => $this->footerShop($navItems),
            'footerColumns' => $this->footerColumns(),
            'supportPhone' => ThemeSettings::supportPhone(),
            'supportEmail' => ThemeSettings::supportEmail(),
            'socialLinks' => ThemeSettings::socialLinks(),
            'appLinks' => ThemeSettings::showDownloadApp() ? array_filter([
                'play_store' => ThemeSettings::playStoreUrl(),
                'app_store' => ThemeSettings::appStoreUrl(),
            ]) : [],
        ];
    }

    /**
     * @return array<int, array{url: string, label: string, active: bool}>
     */
    protected function navItems(): array
    {
        return collect(config('theme.frontend_nav', []))
            ->filter(fn (array $item): bool => (empty($item['module']) || Module::isEnabled($item['module']))
                && Route::has($item['route']))
            ->map(fn (array $item): array => [
                'url' => frontRoute($item['route']),
                'label' => trans($item['label']),
                'active' => request()->routeIs($item['active'] ?? $item['route']),
            ])
            ->values()
            ->all();
    }

    protected function footerAbout(): ?string
    {
        $slug = config('theme.footer.about_block');

        if (! $slug || ! Module::isEnabled('Block')) {
            return null;
        }

        return $this->blocks->getBlockContent($slug, app()->getLocale())?->content;
    }

    /**
     * "Shop" column: top-level categories once the Catalog exists, the main nav until then.
     *
     * @param  array<int, array{url: string, label: string, active: bool}>  $navItems
     * @return array{label: string, links: array<int, array{url: string, label: string}>}
     */
    protected function footerShop(array $navItems): array
    {
        $categories = collect($this->megaMenu->tree())
            ->take((int) config('theme.mega_menu.footer_limit', 6))
            ->map(fn (array $category): array => ['url' => $category['url'], 'label' => $category['name']]);

        if ($categories->isNotEmpty()) {
            return ['label' => trans('theme::frontend.footer.shop'), 'links' => $categories->values()->all()];
        }

        return [
            'label' => trans('theme::frontend.footer.explore'),
            'links' => array_map(fn (array $item): array => ['url' => $item['url'], 'label' => $item['label']], $navItems),
        ];
    }

    /**
     * Configured link columns; links whose route/page is unavailable are dropped, empty columns too.
     *
     * @return array<string, array{label: string, links: array<int, array{url: string, label: string}>}>
     */
    protected function footerColumns(): array
    {
        return collect(config('theme.footer.columns', []))
            ->map(fn (array $column): array => [
                'label' => trans($column['label']),
                'links' => collect($column['links'] ?? [])
                    ->map(fn (array $link): ?array => $this->links->resolve($link))
                    ->filter()
                    ->values()
                    ->all(),
            ])
            ->filter(fn (array $column): bool => $column['links'] !== [])
            ->all();
    }
}

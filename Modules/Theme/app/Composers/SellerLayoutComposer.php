<?php

declare(strict_types=1);

namespace Modules\Theme\Composers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Modules\Theme\Support\StorefrontLinks;
use Modules\Theme\Support\ThemeSettings;

/**
 * Data for the supplier panel layout: the sidebar sections from config('theme.seller_menu')
 * (links whose routes do not exist yet are skipped) and the signed-in seller user.
 */
class SellerLayoutComposer
{
    public function __construct(protected StorefrontLinks $links) {}

    public function compose(View $view): void
    {
        $attributes = request()->attributes;

        if (! $attributes->has('theme.seller_layout')) {
            $attributes->set('theme.seller_layout', $this->resolve());
        }

        $view->with($attributes->get('theme.seller_layout'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolve(): array
    {
        return [
            'siteName' => config('app.name'),
            'logoUrl' => ThemeSettings::logoUrl(),
            'sellerUser' => $this->sellerUser(),
            'sellerMenu' => $this->menu(),
            'sellerDashboardUrl' => $this->links->routeUrl('seller.dashboard'),
            'sellerNotificationsUrl' => $this->links->routeUrl('seller.notifications.index'),
            'sellerProfileUrl' => $this->links->routeUrl('seller.profile.edit'),
            'sellerLogoutUrl' => $this->links->routeUrl('seller.logout'),
        ];
    }

    /**
     * The seller guard is only defined once the Seller module (M03) exists.
     */
    protected function sellerUser(): ?Authenticatable
    {
        return config('auth.guards.seller') ? Auth::guard('seller')->user() : null;
    }

    /**
     * @return array<int, array{label: ?string, items: array<int, array{url: string, label: string, icon: string, active: bool}>}>
     */
    protected function menu(): array
    {
        return collect(config('theme.seller_menu', []))
            ->map(fn (array $section): array => [
                'label' => isset($section['label']) ? trans($section['label']) : null,
                'items' => collect($section['items'] ?? [])
                    ->map(function (array $item): ?array {
                        $url = $this->links->routeUrl($item['route'] ?? null, $item['module'] ?? null);

                        return $url ? [
                            'url' => $url,
                            'label' => trans($item['label']),
                            'icon' => $item['icon'] ?? 'chevron-right',
                            'active' => request()->routeIs($item['active'] ?? $item['route']),
                        ] : null;
                    })
                    ->filter()
                    ->values()
                    ->all(),
            ])
            ->filter(fn (array $section): bool => $section['items'] !== [])
            ->values()
            ->all();
    }
}

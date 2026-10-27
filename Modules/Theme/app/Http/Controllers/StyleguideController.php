<?php

declare(strict_types=1);

namespace Modules\Theme\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;

/**
 * Dev-only gallery of every storefront component with sample data (routes/front.php, local only).
 */
class StyleguideController extends Controller
{
    public function index(): View
    {
        return view('theme::styleguide.index', [
            'products' => $this->sampleProducts(),
            'timeline' => $this->sampleTimeline(),
            'swatches' => [
                ['color' => '#1E3A8A', 'label' => 'Navy', 'selected' => true],
                ['color' => '#B91C1C', 'label' => 'Maroon'],
                ['color' => '#F5F5F4', 'label' => 'Off white'],
                ['color' => '#15803D', 'label' => 'Green', 'disabled' => true],
            ],
        ]);
    }

    /**
     * A dummy supplier-panel page to preview the seller layout.
     */
    public function seller(): View
    {
        return view('theme::styleguide.seller');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function sampleProducts(): array
    {
        $names = [
            'Printed Cotton Straight Kurti with Pockets',
            'Floral Georgette Saree with Blouse Piece',
            'Men Slim Fit Casual Shirt',
            'Kids Cotton Frock, Pack of 2',
            'Stainless Steel Lunch Box Set',
        ];

        return collect($names)->map(fn (string $name, int $index): array => [
            'id' => 9001 + $index,
            'name' => $name,
            'url' => '#product-'.(9001 + $index),
            'image' => null,
            'image_alt2' => null,
            'price' => [249, 499, 399, 329, 599][$index],
            'mrp' => [999, 1299, 399, 699, 899][$index],
            'rating' => [4.1, 3.8, 4.4, 0, 4.0][$index],
            'rating_count' => [2341, 87, 1520, 0, 12][$index],
            'free_delivery' => $index !== 2,
            'cod' => $index % 2 === 0,
            'badge' => $index === 0 ? 'Top Seller' : null,
            'wishlisted' => false,
        ])->all();
    }

    /**
     * @return array<int, array{label: string, at: mixed, state: string, note?: string}>
     */
    protected function sampleTimeline(): array
    {
        return [
            ['label' => 'Ordered', 'at' => Carbon::now()->subDays(3), 'state' => 'done'],
            ['label' => 'Packed', 'at' => Carbon::now()->subDays(2), 'state' => 'done'],
            ['label' => 'Shipped', 'at' => Carbon::now()->subDay(), 'state' => 'current', 'note' => 'In transit to your city hub.'],
            ['label' => 'Out for delivery', 'at' => null, 'state' => 'pending'],
            ['label' => 'Delivered', 'at' => null, 'state' => 'pending'],
        ];
    }
}

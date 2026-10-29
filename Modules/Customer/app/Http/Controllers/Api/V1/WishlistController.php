<?php

declare(strict_types=1);

namespace Modules\Customer\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Api\ApiController;
use Modules\Customer\Exceptions\WishlistException;
use Modules\Customer\Models\Customer;
use Modules\Customer\Services\WishlistService;
use Modules\Storefront\Services\ProductCardPresenter;

/**
 * Wishlist over the API (FR-CUS-006): GET /wishlist (paginated product cards), POST /wishlist
 * {product_id}, DELETE /wishlist/{product}.
 */
class WishlistController extends ApiController
{
    private const PER_PAGE = 24;

    public function __construct(private readonly WishlistService $wishlist) {}

    public function index(Request $request): JsonResponse
    {
        $customer = $this->customer($request);
        $page = $this->wishlist->paginate($customer, self::PER_PAGE, max(1, (int) $request->query('page', 1)));

        $items = class_exists(ProductCardPresenter::class) && $page->items() !== []
            ? array_map(fn (array $card): array => [
                'id' => $card['id'],
                'name' => $card['name'],
                'url' => $card['url'],
                'image' => $card['image'],
                'price' => $card['price']->toDecimal(),
                'mrp' => $card['mrp']->toDecimal(),
                'rating' => $card['rating'],
                'rating_count' => $card['rating_count'],
                'in_stock' => $card['in_stock'],
            ], app(ProductCardPresenter::class)->present($page->items()))
            : array_map(fn (int $id): array => ['id' => $id], $page->items());

        return $this->ok($items, [
            'product_ids' => $this->wishlist->ids($customer),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(['product_id' => ['required', 'integer', 'min:1']]);
        $customer = $this->customer($request);

        try {
            $this->wishlist->set($customer, (int) $validated['product_id'], true);
        } catch (WishlistException $e) {
            return $this->fail(422, $e->getMessage());
        }

        return $this->created(['wishlisted' => true, 'count' => $this->wishlist->count($customer)], message: trans('customer::customer_front.wishlist.added'));
    }

    public function destroy(Request $request): JsonResponse
    {
        $customer = $this->customer($request);
        $this->wishlist->remove($customer, (int) $request->route('product'));

        return $this->ok(['wishlisted' => false, 'count' => $this->wishlist->count($customer)], message: trans('customer::customer_front.wishlist.removed'));
    }

    protected function customer(Request $request): Customer
    {
        /** @var Customer */
        return $request->user('api');
    }
}

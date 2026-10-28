<?php

use Illuminate\Support\Facades\DB;
use Modules\Cart\DTO\CartOwner;
use Modules\Cart\Models\Cart;
use Modules\Cart\Services\CartService;
use Modules\Customer\Models\Customer;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Seller\Models\Seller;
use Modules\User\Models\User;

require_once __DIR__.'/../../Storefront/tests/Helpers.php';

/*
| Shared helpers for the Cart module tests (require_once'd: Pest test files with same-named
| global functions would collide).
*/

if (! function_exists('cartVariant')) {
    /**
     * The single variant of a new live product (₹499, MRP ₹999, 10 in stock unless overridden).
     *
     * @param  array<string, mixed>  $variant  variant columns (price, mrp, stock…)
     * @param  array<string, mixed>  $product  product columns
     */
    function cartVariant(array $variant = [], ?Seller $seller = null, array $product = []): ProductVariant
    {
        $factory = Product::factory()->live()->withVariants(1, $variant);

        if ($seller !== null) {
            $factory = $factory->forSeller($seller);
        }

        return ProductVariant::query()->where('product_id', $factory->create($product)->id)->firstOrFail();
    }

    function cartService(): CartService
    {
        return app(CartService::class);
    }

    function guestOwner(?string $token = null): CartOwner
    {
        return new CartOwner(null, $token);
    }

    function customerOwner(Customer $customer): CartOwner
    {
        return new CartOwner($customer, null);
    }

    /**
     * A guest cart holding the given variants (quantity 1 each, or [variant, qty] pairs).
     *
     * @param  list<ProductVariant|array{0: ProductVariant, 1: int}>  $lines
     */
    function guestCartWith(array $lines): Cart
    {
        $cart = null;

        foreach ($lines as $line) {
            [$variant, $quantity] = is_array($line) ? $line : [$line, 1];
            $cart = cartService()->add(guestOwner($cart?->token), $variant->id, $quantity)->cart;
        }

        return $cart ?? Cart::factory()->create();
    }

    /**
     * Change a variant's live columns directly (tests only).
     *
     * @param  array<string, mixed>  $columns
     */
    function cartUpdateVariant(ProductVariant $variant, array $columns): void
    {
        DB::table('product_variant')->where('id', $variant->id)->update($columns);
    }

    /**
     * Settings > Cart values for this test.
     *
     * @param  array<string, mixed>  $values
     */
    function cartSettings(array $values): void
    {
        $current = json_decode((string) DB::table('settings')->where('name', 'cart')->value('value'), true) ?: [];
        DB::table('settings')->updateOrInsert(
            ['name' => 'cart'],
            ['value' => json_encode(array_merge($current, $values)), 'updated_at' => now(), 'created_at' => now()],
        );
    }

    /**
     * An admin whose role holds every Cart permission.
     */
    function cartAdmin(): User
    {
        $permissions = array_keys(array_merge(...array_values(require base_path('Modules/Cart/config/permissions.php'))));

        $roleId = DB::table('role')->insertGetId([
            'name' => 'Support',
            'slug' => 'support_'.uniqid(),
            'permissions' => json_encode($permissions),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->forceCreate([
            'name' => 'Support',
            'email' => uniqid('cart-admin-').'@example.test',
            'password' => bcrypt('Secret#123'),
            'role_id' => $roleId,
            'status' => 1,
        ]);
    }
}

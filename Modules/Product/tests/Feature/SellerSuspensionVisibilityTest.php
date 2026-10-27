<?php

use Illuminate\Support\Facades\Notification;
use Modules\Product\Enums\ProductStatus;
use Modules\Product\Models\Product;
use Modules\Seller\Services\SellerVerificationService;

require_once __DIR__.'/../../../Seller/tests/Helpers.php';

beforeEach(function () {
    Notification::fake();
    [$this->seller] = sellerWithOwner();
    $this->live = Product::factory()->forSeller($this->seller)->live()->withVariants()->create();
    $this->paused = Product::factory()->forSeller($this->seller)->paused()->withVariants()->create();
    $this->other = Product::factory()->live()->withVariants()->create();
    $this->admin = sellerAdmin();
});

function visibleIds(): array
{
    return Product::query()->visibleOnStorefront()->pluck('id')->all();
}

test('a suspended seller\'s products leave the storefront and come back on reactivation', function () {
    expect(visibleIds())->toContain($this->live->id, $this->other->id)->not->toContain($this->paused->id);

    app(SellerVerificationService::class)->suspend($this->seller, $this->admin, 'Quality review');

    expect(visibleIds())->not->toContain($this->live->id)->toContain($this->other->id)
        ->and($this->live->fresh())->status->toBe(ProductStatus::Live)->seller_active->toBe(2)
        ->and($this->paused->fresh())->status->toBe(ProductStatus::Paused)->seller_active->toBe(2);

    app(SellerVerificationService::class)->reactivate($this->seller->fresh(), $this->admin);

    expect(visibleIds())->toContain($this->live->id)->not->toContain($this->paused->id)
        ->and($this->paused->fresh())->status->toBe(ProductStatus::Paused)->seller_active->toBe(1);
});

test('the admin seller suspension screen hides the products too', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.seller.suspend', [$this->seller->id]), ['reason' => 'Repeated late dispatch'])
        ->assertSessionHas('success');

    expect($this->live->fresh()->isVisibleOnStorefront())->toBeFalse();
});

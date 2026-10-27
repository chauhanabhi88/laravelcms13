<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Modules\Product\Events\VariantLowStock;
use Modules\Product\Models\InventoryMovement;
use Modules\Product\Notifications\LowStockDigest;
use Modules\Product\Services\InventoryService;
use Modules\Seller\Models\SellerUser;

require_once __DIR__.'/../SellerCatalogHelpers.php';

beforeEach(function () {
    $this->catalog = productCatalog();
    [$this->seller, $this->owner] = sellerWithOwner();
    $this->actingAs($this->owner, 'seller');
    $this->product = liveCatalogProduct($this->seller, $this->catalog);
    $this->variant = $this->product->variants()->first();
});

test('the inventory screen lists variants with filters', function () {
    $this->variant->update(['low_stock_threshold' => 20]);

    $this->get(route('seller.inventory.index'))->assertSuccessful()->assertSee('KRT-S-RED')->assertSee('KRT-M-RED');
    $this->get(route('seller.inventory.index', ['stock' => 'low']))->assertSee('KRT-S-RED')->assertDontSee('KRT-M-RED');
    $this->get(route('seller.inventory.index', ['q' => 'M-RED']))->assertSee('KRT-M-RED')->assertDontSee('KRT-S-RED');
    $this->getJson(route('seller.inventory.filters', ['stock' => 'out']))->assertSuccessful()->assertJsonPath('html', fn (string $html) => ! str_contains($html, 'KRT-S-RED'));
});

test('an inline stock edit adjusts the stock through the ledger and fires the low-stock event', function () {
    Event::fake([VariantLowStock::class]);

    $this->patchJson(route('seller.inventory.update', [$this->variant->id]), ['stock' => 3])
        ->assertSuccessful()
        ->assertJsonPath('variant.stock', 3)
        ->assertJsonPath('variant.low', true);

    $movement = InventoryMovement::query()->where('variant_id', $this->variant->id)->latest('id')->first();
    expect($this->variant->fresh()->stock)->toBe(3)
        ->and($movement->type)->toBe(InventoryMovement::TYPE_ADJUST)
        ->and($movement->quantity)->toBe(-7)
        ->and($movement->actor_type)->toBe('seller');

    Event::assertDispatched(VariantLowStock::class, fn (VariantLowStock $event) => $event->variant->id === $this->variant->id && $event->available === 3);
});

test('raising the threshold above the available stock also flags low stock', function () {
    $this->patchJson(route('seller.inventory.update', [$this->variant->id]), ['low_stock_threshold' => 15])->assertSuccessful();

    expect($this->variant->fresh()->low_stock_flagged_at)->not->toBeNull();
});

test('stock cannot drop below what orders hold, and the status toggles', function () {
    app(InventoryService::class)->reserve([['variant_id' => $this->variant->id, 'qty' => 4]]);

    $this->patchJson(route('seller.inventory.update', [$this->variant->id]), ['stock' => 2])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('stock');
    expect($this->variant->fresh()->stock)->toBe(10);

    $this->patchJson(route('seller.inventory.update', [$this->variant->id]), ['status' => (int) config('core.disabled')])->assertSuccessful();
    expect($this->variant->fresh()->isEnabled())->toBeFalse();
});

test('the inventory CSV export lists the seller\'s variants', function () {
    $content = $this->get(route('seller.inventory.export'))->assertSuccessful()->streamedContent();

    expect($content)->toContain('KRT-S-RED')->toContain('KRT-M-RED')->toContain('Seller SKU');
});

test('the daily digest emails low-stock variants to owner and catalog staff, then clears the flags', function () {
    Notification::fake();
    $catalogStaff = SellerUser::factory()->for($this->seller)->role('catalog')->create();
    $ordersStaff = SellerUser::factory()->for($this->seller)->role('orders')->create();

    $this->patchJson(route('seller.inventory.update', [$this->variant->id]), ['stock' => 1])->assertSuccessful();
    expect($this->variant->fresh()->low_stock_flagged_at)->not->toBeNull();

    $this->artisan('product:low-stock-digest')->assertSuccessful();

    Notification::assertSentTo([$this->owner, $catalogStaff], LowStockDigest::class, fn (LowStockDigest $notification) => $notification->variants[0]['sku'] === 'KRT-S-RED' && $notification->variants[0]['available'] === 1);
    Notification::assertNotSentTo($ordersStaff, LowStockDigest::class);
    expect($this->variant->fresh()->low_stock_flagged_at)->toBeNull();

    Notification::fake();
    $this->artisan('product:low-stock-digest')->assertSuccessful();
    Notification::assertNothingSent();
});

<?php

use Illuminate\Support\Facades\Event;
use Modules\Product\Events\VariantLowStock;
use Modules\Product\Exceptions\InsufficientStock;
use Modules\Product\Models\InventoryMovement;
use Modules\Product\Models\Product;
use Modules\Product\Services\InventoryService;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    $this->product = Product::factory()->live()->withVariants(2, ['stock' => 10, 'low_stock_threshold' => 3])->create();
    [$this->first, $this->second] = $this->product->variants()->get()->all();
    $this->inventory = app(InventoryService::class);
});

test('reserve holds stock, writes a movement and refreshes the product stock', function () {
    $admin = productAdmin();

    $this->inventory->reserve([['variant_id' => $this->first->id, 'qty' => 3]], $this->product, $admin, 'Order test');

    expect($this->first->fresh())->stock->toBe(10)->reserved->toBe(3)->available->toBe(7)
        ->and($this->product->fresh()->total_stock)->toBe(17);

    $movement = InventoryMovement::query()->where('variant_id', $this->first->id)->sole();
    expect($movement)
        ->type->toBe('reserve')
        ->bucket->toBe('reserved')
        ->quantity->toBe(3)
        ->stock_after->toBe(10)
        ->reserved_after->toBe(3)
        ->product_id->toBe($this->product->id)
        ->reference_type->toBe($this->product->getMorphClass())
        ->reference_id->toBe($this->product->id)
        ->actor_type->toBe('admin')
        ->actor_id->toBe($admin->id)
        ->note->toBe('Order test');
});

test('a reservation that one line cannot cover writes nothing at all', function () {
    try {
        $this->inventory->reserve([
            ['variant_id' => $this->first->id, 'qty' => 2],
            ['variant_id' => $this->second->id, 'qty' => 11],
        ]);
        $this->fail('InsufficientStock was not thrown.');
    } catch (InsufficientStock $e) {
        expect($e)->variantId->toBe($this->second->id)->requested->toBe(11)->available->toBe(10);
    }

    expect($this->first->fresh()->reserved)->toBe(0)
        ->and($this->second->fresh()->reserved)->toBe(0)
        ->and(InventoryMovement::query()->whereIn('variant_id', [$this->first->id, $this->second->id])->count())->toBe(0);
});

test('repeated lines of one variant are summed against its availability', function () {
    expect(fn () => $this->inventory->reserve([
        ['variant_id' => $this->first->id, 'qty' => 6],
        ['variant_id' => $this->first->id, 'qty' => 5],
    ]))->toThrow(InsufficientStock::class);

    $this->inventory->reserve([
        ['variant_id' => $this->first->id, 'qty' => 6],
        ['variant_id' => $this->first->id, 'qty' => 4],
    ]);

    expect($this->first->fresh()->reserved)->toBe(10)
        ->and(InventoryMovement::query()->where('variant_id', $this->first->id)->sole()->quantity)->toBe(10);
});

test('invalid lines are refused', function (array $line) {
    expect(fn () => $this->inventory->reserve([$line]))->toThrow(InvalidArgumentException::class);
})->with([
    'zero quantity' => [['variant_id' => 1, 'qty' => 0]],
    'negative quantity' => [['variant_id' => 1, 'qty' => -2]],
    'string quantity' => [['variant_id' => 1, 'qty' => '2']],
    'no variant' => [['qty' => 1]],
]);

test('release gives held stock back but never below zero', function () {
    $this->inventory->reserve([['variant_id' => $this->first->id, 'qty' => 2]]);
    $this->inventory->release([['variant_id' => $this->first->id, 'qty' => 5]]);

    expect($this->first->fresh())->reserved->toBe(0)->stock->toBe(10);

    $release = InventoryMovement::query()->where('variant_id', $this->first->id)->where('type', 'release')->sole();
    expect($release->quantity)->toBe(-2);

    // Nothing left to release: no movement is written.
    $this->inventory->release([['variant_id' => $this->first->id, 'qty' => 1]]);
    expect(InventoryMovement::query()->where('variant_id', $this->first->id)->where('type', 'release')->count())->toBe(1);
});

test('commit takes the units out of both stock and reserved', function () {
    $this->inventory->reserve([['variant_id' => $this->first->id, 'qty' => 4]]);
    $this->inventory->commit([['variant_id' => $this->first->id, 'qty' => 4]], $this->product);

    expect($this->first->fresh())->stock->toBe(6)->reserved->toBe(0)->available->toBe(6);

    $rows = InventoryMovement::query()->where('variant_id', $this->first->id)->where('type', 'commit')->orderBy('id')->get();
    expect($rows->pluck('bucket')->all())->toBe(['reserved', 'stock'])
        ->and($rows->pluck('quantity')->all())->toBe([-4, -4])
        ->and($rows->last())->stock_after->toBe(6)->reserved_after->toBe(0);

    expect(fn () => $this->inventory->commit([['variant_id' => $this->first->id, 'qty' => 7]]))->toThrow(InsufficientStock::class);
    expect($this->first->fresh()->stock)->toBe(6);
});

test('restock and adjust record the change as a signed delta', function () {
    $this->inventory->restock($this->first, 2, null, null, 'Return QC passed');
    $this->inventory->adjust($this->first->id, 5, null, 'Stock count');

    expect($this->first->fresh()->stock)->toBe(5);

    $rows = InventoryMovement::query()->where('variant_id', $this->first->id)->orderBy('id')->get();
    expect($rows->pluck('type')->all())->toBe(['restock', 'adjust'])
        ->and($rows->pluck('quantity')->all())->toBe([2, -7])
        ->and($rows->pluck('stock_after')->all())->toBe([12, 5]);

    // Same count again: nothing to record.
    $this->inventory->adjust($this->first->id, 5);
    expect(InventoryMovement::query()->where('variant_id', $this->first->id)->count())->toBe(2);
});

test('stock cannot be adjusted below what orders hold', function () {
    $this->inventory->reserve([['variant_id' => $this->first->id, 'qty' => 4]]);

    expect(fn () => $this->inventory->adjust($this->first->id, 3))->toThrow(InsufficientStock::class)
        ->and(fn () => $this->inventory->adjust($this->first->id, -1))->toThrow(InvalidArgumentException::class);

    $this->inventory->adjust($this->first->id, 4);
    expect($this->first->fresh())->stock->toBe(4)->available->toBe(0);
});

test('the low-stock event fires once, when availability crosses the threshold', function () {
    Event::fake([VariantLowStock::class]);

    $this->inventory->reserve([['variant_id' => $this->first->id, 'qty' => 5]]);
    Event::assertNotDispatched(VariantLowStock::class);

    $this->inventory->reserve([['variant_id' => $this->first->id, 'qty' => 2]]);
    $this->inventory->reserve([['variant_id' => $this->first->id, 'qty' => 1]]);

    Event::assertDispatchedTimes(VariantLowStock::class, 1);
    Event::assertDispatched(VariantLowStock::class, fn (VariantLowStock $event) => $event->variant->id === $this->first->id && $event->available === 3);

    // Back above the threshold and down again fires a second time.
    $this->inventory->release([['variant_id' => $this->first->id, 'qty' => 8]]);
    $this->inventory->reserve([['variant_id' => $this->first->id, 'qty' => 8]]);
    Event::assertDispatchedTimes(VariantLowStock::class, 2);
});

<?php

use Modules\Product\Enums\ProductStatus;
use Modules\Product\Models\Product;
use Modules\Product\Services\InventoryService;
use Modules\Product\Services\ProductFactoryService;
use Modules\Product\Services\ProductModerationService;
use Modules\Seller\Models\Seller;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    $this->admin = productAdmin();
});

test('the product screens need their permissions', function () {
    $admin = productAdmin(['admin.product.index']);
    $product = Product::factory()->pendingQc()->withVariants()->create();

    $this->actingAs($admin)->get(route('admin.product.index'))->assertSuccessful();
    $this->actingAs($admin)->get(route('admin.product.show', [$product->id]))->assertForbidden();
    $this->actingAs($admin)->get(route('admin.product_qc.index'))->assertForbidden();
    $this->actingAs($admin)->get(route('admin.product_qc.review', [$product->id]))->assertForbidden();
    $this->actingAs($admin)->post(route('admin.product_qc.approve', [$product->id]))->assertForbidden();
    $this->actingAs($admin)->post(route('admin.product_qc.bulk'), ['ids' => [$product->id], 'action' => 'approve'])->assertForbidden();
    $this->actingAs($admin)->get(route('admin.inventory_movement.index'))->assertForbidden();

    expect($product->fresh()->status)->toBe(ProductStatus::PendingQc);
});

test('the queue lists submissions and live edits, oldest first', function () {
    $oldest = Product::factory()->pendingQc()->withVariants()->create(['name' => 'Queue Oldest Saree', 'submitted_at' => now()->subHours(80)]);
    $newest = Product::factory()->pendingQc()->withVariants()->create(['name' => 'Queue Newest Saree', 'submitted_at' => now()->subHour()]);
    $edited = Product::factory()->live()->withVariants()->create([
        'name' => 'Queue Edited Saree',
        'has_pending_changes' => 1,
        'pending_changes' => ['translations' => ['en' => ['name' => 'Queue Edited Saree v2', 'description' => null, 'highlights' => null, 'meta_title' => null, 'meta_description' => null]]],
        'submitted_at' => now()->subHours(5),
    ]);
    Product::factory()->withVariants()->create(['name' => 'Queue Draft Saree']);
    Product::factory()->live()->withVariants()->create(['name' => 'Queue Live Saree']);

    $this->actingAs($this->admin)
        ->get(route('admin.product_qc.index'))
        ->assertSuccessful()
        ->assertSeeInOrder(['Queue Oldest Saree', 'Queue Edited Saree', 'Queue Newest Saree'])
        ->assertSee(trans('product::product.labels.overdue'))
        ->assertDontSee('Queue Draft Saree')
        ->assertDontSee('Queue Live Saree');

    $this->actingAs($this->admin)
        ->post(route('admin.product_qc.filters'), ['queue_type' => 'changes'])
        ->assertSuccessful()
        ->assertSee('Queue Edited Saree')
        ->assertDontSee('Queue Oldest Saree');

    $this->actingAs($this->admin)
        ->get(route('admin.product_qc.review', [$edited->id]))
        ->assertSuccessful()
        ->assertSee(trans('product::product.titles.pending_changes'))
        ->assertSee('Queue Edited Saree v2')
        ->assertSee(route('admin.product_qc.review', [$oldest->id]), false)
        ->assertSee(route('admin.product_qc.review', [$newest->id]), false);
});

test('a moderator approves and rejects from the review screen', function () {
    $catalog = productCatalog();
    $seller = Seller::factory()->active()->create();
    $first = app(ProductModerationService::class)->submit(app(ProductFactoryService::class)->create(productData($seller, $catalog)));
    $second = app(ProductModerationService::class)->submit(app(ProductFactoryService::class)->create(productData($seller, $catalog, ['variants' => [productVariant($catalog, 'B-1', 'L', 'Blue')]])));

    $this->actingAs($this->admin)
        ->get(route('admin.product_qc.review', [$first->id]))
        ->assertSuccessful()
        ->assertSee('Printed Cotton Kurti')
        ->assertSee('KRT-S-RED')
        ->assertSee('Cotton')
        ->assertSee(trans('product::product.reason_templates.watermark'));

    // Approve opens the next product still waiting.
    $this->actingAs($this->admin)
        ->post(route('admin.product_qc.approve', [$first->id]))
        ->assertRedirect(route('admin.product_qc.review', [$second->id]))
        ->assertSessionHas('success');
    expect($first->fresh()->status)->toBe(ProductStatus::Live);

    $this->actingAs($this->admin)
        ->post(route('admin.product_qc.reject', [$second->id]), ['reason' => ''])
        ->assertRedirect(route('admin.product_qc.review', [$second->id]))
        ->assertSessionHas('error');
    expect($second->fresh()->status)->toBe(ProductStatus::PendingQc);

    $this->actingAs($this->admin)
        ->post(route('admin.product_qc.reject', [$second->id]), ['reason_codes' => ['watermark']])
        ->assertRedirect(route('admin.product_qc.index'))
        ->assertSessionHas('success');
    expect($second->fresh())->status->toBe(ProductStatus::Rejected)->qc_notes->toBe(trans('product::product.reason_templates.watermark'));
});

test('bulk approve moves the selected products live', function () {
    $first = Product::factory()->pendingQc()->withVariants()->create();
    $second = Product::factory()->pendingQc()->withVariants()->create();
    $draft = Product::factory()->withVariants()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.product_qc.bulk'), ['ids' => [$first->id, $second->id, $draft->id], 'action' => 'approve'])
        ->assertRedirect(route('admin.product_qc.index'))
        ->assertSessionHas('error', fn (string $message) => str_contains($message, '2 done, 1 failed'));

    expect($first->fresh()->status)->toBe(ProductStatus::Live)
        ->and($second->fresh()->status)->toBe(ProductStatus::Live)
        ->and($draft->fresh()->status)->toBe(ProductStatus::Draft);

    $third = Product::factory()->pendingQc()->withVariants()->create();
    $this->actingAs($this->admin)
        ->postJson(route('admin.product_qc.bulk'), ['ids' => [$third->id], 'action' => 'reject', 'reason' => 'Wrong size chart'])
        ->assertSuccessful()
        ->assertJsonPath('type', 'success');
    expect($third->fresh())->status->toBe(ProductStatus::Rejected)->qc_notes->toBe('Wrong size chart');

    $this->actingAs($this->admin)->post(route('admin.product_qc.bulk'), ['ids' => [], 'action' => 'approve'])->assertSessionHasErrors('ids');
});

test('the product grid, detail page and export work', function () {
    $product = Product::factory()->live()->withVariants(2)->create(['name' => 'Grid Linen Shirt']);
    Product::factory()->withVariants()->create(['name' => 'Grid Draft Shirt']);
    app(InventoryService::class)->reserve([['variant_id' => $product->variants()->first()->id, 'qty' => 2]]);

    $this->actingAs($this->admin)->get(route('admin.product.index'))
        ->assertSuccessful()
        ->assertSee('Grid Linen Shirt')
        ->assertSee('Grid Draft Shirt')
        ->assertSee('₹499');

    $this->actingAs($this->admin)->post(route('admin.product.filters'), ['status' => 'live', 'search' => 'Grid'])
        ->assertSuccessful()
        ->assertSee('Grid Linen Shirt')
        ->assertDontSee('Grid Draft Shirt');

    $this->actingAs($this->admin)->post(route('admin.product.filters'), ['stock' => 'out_of_stock', 'search' => 'Grid'])
        ->assertSuccessful()
        ->assertDontSee('Grid Linen Shirt');

    $this->actingAs($this->admin)->get(route('admin.product.show', [$product->id]))
        ->assertSuccessful()
        ->assertSee('Grid Linen Shirt')
        ->assertSee($product->variants()->first()->sku)
        ->assertSee(trans('product::product.movement_types.reserve'));

    $this->actingAs($this->admin)->get(route('admin.product.export'))
        ->assertSuccessful()
        ->assertDownload();

    $this->actingAs($this->admin)->get(route('admin.inventory_movement.index', ['product_id' => $product->id]))
        ->assertSuccessful()
        ->assertSee(trans('product::product.movement_types.reserve'));
});

test('an admin blocks and unblocks a product', function () {
    $product = Product::factory()->live()->withVariants()->create();

    $this->actingAs($this->admin)->post(route('admin.product.block', [$product->id]), ['reason' => ''])->assertSessionHas('error');
    expect($product->fresh()->status)->toBe(ProductStatus::Live);

    $this->actingAs($this->admin)
        ->post(route('admin.product.block', [$product->id]), ['reason' => 'Trademark complaint'])
        ->assertRedirect(route('admin.product.show', [$product->id]))
        ->assertSessionHas('success');
    expect($product->fresh())->status->toBe(ProductStatus::Blocked)->qc_notes->toBe('Trademark complaint');

    $this->actingAs($this->admin)->get(route('admin.product.show', [$product->id]))->assertSuccessful()->assertSee('Trademark complaint');

    $this->actingAs($this->admin)->post(route('admin.product.unblock', [$product->id]))->assertSessionHas('success');
    expect($product->fresh()->status)->toBe(ProductStatus::PendingQc);
});

<?php

use Illuminate\Support\Facades\Event;
use Modules\Core\Exceptions\InvalidStateTransition;
use Modules\Product\DTO\ProductData;
use Modules\Product\Enums\ProductStatus;
use Modules\Product\Events\ProductApproved;
use Modules\Product\Events\ProductBlocked;
use Modules\Product\Events\ProductRejected;
use Modules\Product\Events\ProductSubmitted;
use Modules\Product\Exceptions\ProductException;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductImage;
use Modules\Product\Services\ProductFactoryService;
use Modules\Product\Services\ProductModerationService;
use Modules\Seller\Models\Seller;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    $this->catalog = productCatalog();
    $this->seller = Seller::factory()->active()->create();
    $this->moderation = app(ProductModerationService::class);
    $this->admin = productAdmin();
    $this->product = app(ProductFactoryService::class)->create(productData($this->seller, $this->catalog));
});

test('a submission needs an enabled variant and an image', function () {
    ProductImage::query()->where('product_id', $this->product->id)->delete();

    expect(fn () => $this->moderation->submit($this->product))->toThrow(ProductException::class, 'at least one image');
    expect($this->moderation->missingForSubmission($this->product))->toHaveCount(1);
});

test('draft → pending QC → live writes history and fires events', function () {
    Event::fake([ProductSubmitted::class, ProductApproved::class]);

    $this->moderation->submit($this->product);
    expect($this->product->fresh())->status->toBe(ProductStatus::PendingQc)->submitted_at->not->toBeNull();

    $this->moderation->approve($this->product, $this->admin, 'Looks good');

    $product = $this->product->fresh();
    expect($product)
        ->status->toBe(ProductStatus::Live)
        ->published_at->not->toBeNull()
        ->qc_reviewed_by->toBe($this->admin->id)
        ->qc_notes->toBeNull()
        ->and($product->isVisibleOnStorefront())->toBeTrue()
        ->and(Product::query()->visibleOnStorefront()->whereKey($product->id)->exists())->toBeTrue();

    $history = $product->statusHistories()->get();
    expect($history->pluck('to_status')->all())->toBe(['pending_qc', 'live'])
        ->and($history->last())->actor_type->toBe('admin')->actor_id->toBe($this->admin->id)->reason->toBe('Looks good');

    Event::assertDispatched(ProductSubmitted::class);
    Event::assertDispatched(ProductApproved::class, fn (ProductApproved $event) => $event->product->is($product) && ! $event->changesOnly);
});

test('transitions outside the graph are refused', function () {
    expect(fn () => $this->moderation->approve($this->product, $this->admin))->toThrow(ProductException::class)
        ->and(fn () => $this->moderation->pause($this->product))->toThrow(InvalidStateTransition::class)
        ->and(fn () => $this->moderation->block($this->product, $this->admin, 'Policy'))->toThrow(InvalidStateTransition::class)
        ->and(fn () => $this->moderation->unblock($this->product, $this->admin))->toThrow(InvalidStateTransition::class);

    $this->moderation->submit($this->product);
    expect(fn () => $this->moderation->submit($this->product))->toThrow(InvalidStateTransition::class);

    expect($this->product->fresh()->status)->toBe(ProductStatus::PendingQc);
});

test('a rejection needs a reason, shows it to the seller and allows a resubmission', function () {
    Event::fake([ProductRejected::class]);
    $this->moderation->submit($this->product);

    expect(fn () => $this->moderation->reject($this->product, $this->admin, '  '))->toThrow(ProductException::class);

    $this->moderation->reject($this->product, $this->admin, 'Use a plain background.', ['blurry_images', 'not_a_template']);

    expect($this->product->fresh())
        ->status->toBe(ProductStatus::Rejected)
        ->qc_notes->toBe('Images are blurry or too small. Use a plain background.');

    Event::assertDispatched(ProductRejected::class, fn (ProductRejected $event) => $event->reasonCodes === ['blurry_images'] && ! $event->changesOnly);

    $this->moderation->submit($this->product);
    expect($this->product->fresh()->status)->toBe(ProductStatus::PendingQc);
});

test('approving pending changes writes them into the live content', function () {
    Event::fake([ProductApproved::class]);
    $product = $this->moderation->approve($this->moderation->submit($this->product), $this->admin);

    $edit = productEditData($product->fresh(), $this->catalog, ['translations' => ['en' => ['name' => 'Festive Cotton Kurti', 'highlights' => ['Festive print']]]]);
    $edit = new ProductData(
        sellerId: $edit->sellerId,
        categoryId: $edit->categoryId,
        translations: $edit->translations,
        variants: [...$edit->variants, productVariant($this->catalog, 'KRT-L-BLUE', 'L', 'Blue', '599', '1199', 5)],
        images: $edit->images,
        attributes: [$this->catalog['fabric'] => $this->catalog['options']['Rayon']],
    );
    app(ProductFactoryService::class)->update($product, $edit);

    expect($product->fresh()->isInQcQueue())->toBeTrue();

    $this->moderation->approve($product, $this->admin);

    $approved = app(ProductFactoryService::class)->load($product->id);
    expect($approved)
        ->status->toBe(ProductStatus::Live)
        ->name->toBe('Festive Cotton Kurti')
        ->highlights->toBe(['Festive print'])
        ->has_pending_changes->toBe(2)
        ->pending_changes->toBeNull()
        ->and($approved->variants)->toHaveCount(3)
        ->and($approved->variants->last())->seller_sku->toBe('KRT-L-BLUE')->stock->toBe(5)
        ->and($approved->attributeValues->pluck('attribute_option_id')->all())->toBe([$this->catalog['options']['Rayon']])
        ->and($approved->total_stock)->toBe(25)
        ->and($approved->statusHistories()->get()->last()->meta)->toBe(['action' => 'changes_approved']);

    Event::assertDispatched(ProductApproved::class, fn (ProductApproved $event) => $event->changesOnly);
});

test('rejecting pending changes keeps the live content', function () {
    $product = $this->moderation->approve($this->moderation->submit($this->product), $this->admin);
    app(ProductFactoryService::class)->update($product, productEditData($product->fresh(), $this->catalog, ['translations' => ['en' => ['name' => 'Cheapest Kurti Ever']]]));

    $this->moderation->reject($product, $this->admin, null, ['incomplete_details']);

    expect(app(ProductFactoryService::class)->load($product->id))
        ->status->toBe(ProductStatus::Live)
        ->name->toBe('Printed Cotton Kurti')
        ->has_pending_changes->toBe(2)
        ->pending_changes->toBeNull()
        ->qc_notes->toBe('Product details are incomplete or unclear');

    expect(fn () => $this->moderation->reject($product, $this->admin, 'Again'))->toThrow(ProductException::class);
});

test('block needs a reason and unblock sends the product back to QC', function () {
    Event::fake([ProductBlocked::class, ProductSubmitted::class]);
    $product = $this->moderation->approve($this->moderation->submit($this->product), $this->admin);

    expect(fn () => $this->moderation->block($product, $this->admin, ''))->toThrow(ProductException::class);

    $this->moderation->block($product, $this->admin, 'Counterfeit complaint');
    expect($product->fresh())
        ->status->toBe(ProductStatus::Blocked)
        ->qc_notes->toBe('Counterfeit complaint')
        ->and(Product::query()->visibleOnStorefront()->whereKey($product->id)->exists())->toBeFalse();
    Event::assertDispatched(ProductBlocked::class);

    $this->moderation->unblock($product, $this->admin, 'Invoice checked');
    expect($product->fresh()->status)->toBe(ProductStatus::PendingQc);
});

test('the seller can pause and resume a live product', function () {
    $product = $this->moderation->approve($this->moderation->submit($this->product), $this->admin);

    $this->moderation->pause($product);
    expect($product->fresh())->status->toBe(ProductStatus::Paused)
        ->and(Product::query()->visibleOnStorefront()->whereKey($product->id)->exists())->toBeFalse();

    expect(fn () => $this->moderation->pause($product))->toThrow(InvalidStateTransition::class);

    $this->moderation->resume($product);
    expect($product->fresh()->status)->toBe(ProductStatus::Live);
});

test('trusted sellers are approved automatically when the setting is on', function () {
    $this->seller->forceFill(['rating_avg' => '4.50', 'rating_count' => 12])->save();

    $this->moderation->submit($this->product);
    expect($this->product->fresh()->status)->toBe(ProductStatus::PendingQc);

    $second = app(ProductFactoryService::class)->create(productData($this->seller, $this->catalog, ['variants' => [productVariant($this->catalog, 'NEW-1', 'L', 'Blue')]]));
    config(['product.defaults.auto_approve_trusted_sellers' => 1]);

    $this->moderation->submit($second);
    expect($second->fresh())->status->toBe(ProductStatus::Live)->published_at->not->toBeNull();

    $this->seller->forceFill(['rating_avg' => '4.29'])->save();
    $third = app(ProductFactoryService::class)->create(productData($this->seller, $this->catalog, ['variants' => [productVariant($this->catalog, 'NEW-2', 'L', 'Red')]]));
    $this->moderation->submit($third);
    expect($third->fresh()->status)->toBe(ProductStatus::PendingQc);
});

test('bulk decisions report what could not be done', function () {
    $this->moderation->submit($this->product);
    $draft = app(ProductFactoryService::class)->create(productData($this->seller, $this->catalog, ['variants' => [productVariant($this->catalog, 'NEW-3', 'L', 'Red')]]));

    $result = $this->moderation->bulkApprove([$this->product->id, $draft->id, 999999], $this->admin);

    expect($result['done'])->toBe([$this->product->id])
        ->and(array_keys($result['failed']))->toEqualCanonicalizing([$draft->id, 999999])
        ->and($this->product->fresh()->status)->toBe(ProductStatus::Live);

    expect(fn () => $this->moderation->bulkReject([$draft->id], $this->admin, null))->toThrow(ProductException::class);
});

<?php

use Illuminate\Support\Carbon;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Core\Support\Money;
use Modules\Finance\Models\CommissionRule;
use Modules\Finance\Models\FeeRule;
use Modules\Finance\Services\CommissionResolver;
use Modules\Finance\Services\FeeCalculatorImpl;
use Modules\Order\Models\SalesOrderItem;
use Modules\Product\Contracts\FeeCalculator;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    financeSetup();
    // The seeded global 0 % rule is in force; a test-local global rule makes outcomes explicit.
    CommissionRule::query()->where('scope', 'global')->update(['status' => config('core.disabled')]);

    $this->root = CatalogCategory::factory()->create();
    $this->child = CatalogCategory::factory()->create(['parent_id' => $this->root->id]);
    $this->leaf = CatalogCategory::factory()->create(['parent_id' => $this->child->id]);
    $this->leaf->forceFill(['path' => '/'.$this->root->id.'/'.$this->child->id.'/'.$this->leaf->id.'/'])->save();
    $this->seller = financeSeller();
    $this->resolver = app(CommissionResolver::class);
});

test('the most specific rule wins: seller + category > seller > category > global', function () {
    $global = CommissionRule::factory()->percent('1')->create();
    expect($this->resolver->for($this->seller, $this->leaf)->ruleId)->toBe($global->id);

    $category = CommissionRule::factory()->forCategory($this->root)->percent('2')->create();
    expect($this->resolver->for($this->seller, $this->leaf)->ruleId)->toBe($category->id);

    $seller = CommissionRule::factory()->forSeller($this->seller)->percent('3')->create();
    expect($this->resolver->for($this->seller, $this->leaf)->ruleId)->toBe($seller->id);

    $sellerCategory = CommissionRule::factory()->forSellerCategory($this->seller, $this->child)->percent('4')->create();
    expect($this->resolver->for($this->seller, $this->leaf)->ruleId)->toBe($sellerCategory->id)
        // Another seller still gets the category rule.
        ->and($this->resolver->for(financeSeller(), $this->leaf)->ruleId)->toBe($category->id);
});

test('category rules are inherited from the nearest ancestor', function () {
    CommissionRule::factory()->forCategory($this->root)->percent('2')->create();
    $nearer = CommissionRule::factory()->forCategory($this->child)->percent('6')->create();

    expect($this->resolver->for($this->seller, $this->leaf)->ruleId)->toBe($nearer->id)
        ->and($this->resolver->for($this->seller, $this->root)->value)->toBe('2.00');
});

test('only active rules in force on the date apply', function () {
    $old = CommissionRule::factory()->percent('5')->between('2025-01-01', '2025-12-31')->create();
    $current = CommissionRule::factory()->percent('7')->between('2026-01-01', null)->create();
    CommissionRule::factory()->forSeller($this->seller)->percent('9')->create(['status' => config('core.disabled')]);

    expect($this->resolver->for($this->seller, $this->leaf, Carbon::parse('2025-06-01'))->ruleId)->toBe($old->id)
        ->and($this->resolver->for($this->seller, $this->leaf, Carbon::parse('2026-06-01'))->ruleId)->toBe($current->id)
        ->and($this->resolver->for($this->seller, $this->leaf, Carbon::parse('2024-06-01')))->toBeNull();
});

test('percent, flat and taxable-value commissions per unit', function () {
    $percent = CommissionRule::factory()->percent('10')->create();
    $taxable = CommissionRule::factory()->forSeller($this->seller)->percent('10')->create(['applies_to' => CommissionRule::APPLIES_TO_TAXABLE_VALUE]);
    $flat = CommissionRule::factory()->forSellerCategory($this->seller, $this->leaf)->flat('25')->create();

    $price = Money::of('1050');

    expect($this->resolver->perUnit($price, $this->resolver->for(financeSeller(), null))->toDecimal())->toBe('105.00')
        ->and($this->resolver->perUnit($price, $this->resolver->for($this->seller, null), '5')->toDecimal())->toBe('100.00')
        ->and($this->resolver->perUnit($price, $this->resolver->for($this->seller, $this->leaf))->toDecimal())->toBe('25.00')
        ->and($percent->id)->not->toBe($taxable->id)->not->toBe($flat->id);
});

test('the Product FeeCalculator is the commission engine, with GST on fees as platform fee', function () {
    CommissionRule::factory()->forSeller($this->seller)->percent('10')->create();
    FeeRule::factory()->forSeller($this->seller)->create(['value' => '5']);

    $fees = app(FeeCalculator::class)->fees(Money::of('1000'), $this->leaf, $this->seller);

    expect(app(FeeCalculator::class))->toBeInstanceOf(FeeCalculatorImpl::class)
        ->and($fees['commission']->toDecimal())->toBe('100.00')
        // 5 closing fee + 18 % GST on (100 + 5).
        ->and($fees['platform_fee']->toDecimal())->toBe('23.90');
});

test('order items keep their commission snapshot when the rule changes later', function () {
    CommissionRule::query()->where('scope', 'global')->update(['status' => config('core.enabled')]);
    $rule = CommissionRule::factory()->forSeller($this->seller)->percent('10')->create();

    [, $sellerOrder] = financeDeliveredOrder($this->seller, '500');
    $rule->update(['value' => '20']);

    $item = SalesOrderItem::query()->where('seller_order_id', $sellerOrder->id)->firstOrFail();

    expect($item->commission_amount->toDecimal())->toBe('50.00')
        ->and($item->commission_rule_id)->toBe($rule->id)
        ->and(ledgerByType($sellerOrder)['commission']->amount->toDecimal())->toBe('50.00');
});

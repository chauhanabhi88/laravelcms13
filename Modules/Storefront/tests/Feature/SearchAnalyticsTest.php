<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Storefront\Models\SearchQueryLog;
use Modules\Storefront\Models\SearchTermStat;
use Modules\Storefront\Services\Search\SearchAnalytics;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    Notification::fake();
});

test('a search is logged after the response, normalised, with its result count', function () {
    storefrontProduct(['name' => 'Printed Cotton Kurti']);

    $this->get(frontRoute('storefront.search').'?'.http_build_query(['q' => '  Cotton   KURTI ']))->assertSuccessful();

    $log = SearchQueryLog::query()->sole();
    expect($log->term)->toBe('cotton kurti')
        ->and($log->results_count)->toBe(1)
        ->and($log->session_id)->not->toBeNull();
});

test('filter changes, later pages and listings without a term are not logged', function () {
    storefrontProduct(['name' => 'Kurti One']);

    $this->getJson(frontRoute('storefront.search').'?q=kurti&partial=1')->assertSuccessful();
    $this->getJson(frontRoute('storefront.search').'?q=kurti&partial=grid&page=2')->assertSuccessful();
    $this->get(frontRoute('storefront.search'))->assertSuccessful();

    expect(SearchQueryLog::query()->count())->toBe(0);
});

test('a result click is attached to the session\'s latest search for the term', function () {
    $product = storefrontProduct(['name' => 'Kurti One']);
    $analytics = app(SearchAnalytics::class);
    $analytics->log('kurti', 3, null, 'session-a');
    $analytics->log('kurti', 3, null, 'session-b');

    expect($analytics->recordClick('Kurti', $product->id, 'session-a'))->toBeTrue()
        ->and(SearchQueryLog::query()->where('session_id', 'session-a')->value('clicked_product_id'))->toBe($product->id)
        ->and(SearchQueryLog::query()->where('session_id', 'session-b')->value('clicked_product_id'))->toBeNull();
});

test('the click beacon endpoint validates its input', function () {
    $this->post(frontRoute('storefront.search.click'), ['q' => 'kurti', 'product_id' => 5])->assertNoContent();
    $this->postJson(frontRoute('storefront.search.click'), ['q' => ''])->assertUnprocessable();
});

test('the nightly aggregation builds term statistics and prunes old logs', function () {
    $rows = [
        ['kurti', 4, null, now()->subDays(2)],
        ['kurti', 0, 12, now()->subDays(10)],
        ['saree', 0, null, now()->subDays(3)],
        ['old term', 3, null, now()->subDays(120)],
    ];
    foreach ($rows as [$term, $results, $clicked, $at]) {
        DB::table('search_query_log')->insert(['term' => $term, 'results_count' => $results, 'clicked_product_id' => $clicked, 'created_at' => $at]);
    }

    $this->artisan('storefront:aggregate-search-terms')->assertSuccessful();

    $kurti = SearchTermStat::query()->find('kurti');
    expect($kurti)->searches_7d->toBe(1)->searches_30d->toBe(2)->avg_results->toBe(2)->zero_result_count->toBe(1)->clicks_30d->toBe(1)
        ->and($kurti->clickThroughRate())->toBe(50.0)
        ->and(SearchTermStat::query()->find('saree')->zero_result_count)->toBe(1)
        ->and(SearchTermStat::query()->find('old term'))->toBeNull()
        ->and(DB::table('search_query_log')->where('term', 'old term')->exists())->toBeFalse();
});

test('the admin search report lists top, zero-result and low click-through terms', function () {
    SearchTermStat::query()->create(['term' => 'kurti', 'searches_7d' => 9, 'searches_30d' => 20, 'avg_results' => 4, 'clicks_30d' => 5, 'updated_at' => now()]);
    SearchTermStat::query()->create(['term' => 'blue jhola', 'searches_7d' => 2, 'searches_30d' => 3, 'avg_results' => 0, 'zero_result_count' => 3, 'updated_at' => now()]);

    $this->actingAs(storefrontAdmin())
        ->get(route('admin.search_report.index'))
        ->assertSuccessful()
        ->assertSee('Top searches')
        ->assertSee('kurti')
        ->assertSee('blue jhola')
        ->assertSee('25%');
});

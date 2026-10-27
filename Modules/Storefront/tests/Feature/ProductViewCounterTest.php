<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Storefront\Services\ProductViewCounter;
use Modules\Storefront\Support\StorefrontUrls;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    Notification::fake();
});

function viewRequest(string $userAgent = 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/126.0 Mobile Safari/537.36'): Request
{
    $request = Request::create('/p/test-1', 'GET', server: ['HTTP_USER_AGENT' => $userAgent]);
    $request->setLaravelSession(app('session')->driver());

    return $request;
}

test('a view is counted once per visitor per product within the window', function () {
    $counter = app(ProductViewCounter::class);
    $request = viewRequest();

    expect($counter->hit($request, 7))->toBeTrue()
        ->and($counter->hit($request, 7))->toBeFalse()
        ->and($counter->hit($request, 8))->toBeTrue()
        ->and($counter->pending())->toBe([7 => 1, 8 => 1]);

    $this->travel(61)->minutes();

    expect($counter->hit($request, 7))->toBeTrue()
        ->and($counter->pending())->toBe([7 => 2, 8 => 1]);
});

test('bots and empty user agents are not counted', function (string $userAgent) {
    expect(app(ProductViewCounter::class)->hit(viewRequest($userAgent), 7))->toBeFalse()
        ->and(app(ProductViewCounter::class)->pending())->toBe([]);
})->with([
    'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
    'facebookexternalhit/1.1',
    'curl/8.4.0',
    '',
]);

test('the flush command adds the collected views to the product and resets the counters', function () {
    ['product' => $product] = pdpProduct();
    $counter = app(ProductViewCounter::class);

    foreach (range(1, 3) as $visitor) {
        $request = viewRequest();
        $request->setLaravelSession(app('session')->driver('array'));
        $request->session()->flush();
        $counter->hit($request, $product->id);
    }

    $this->artisan('storefront:flush-views')->expectsOutput('Recorded 3 product views.')->assertSuccessful();

    expect((int) DB::table('product')->where('id', $product->id)->value('view_count'))->toBe(3)
        ->and($counter->pending())->toBe([]);

    $this->artisan('storefront:flush-views')->expectsOutput('Recorded 0 product views.')->assertSuccessful();
});

test('opening the product page counts a view, previews do not', function () {
    ['product' => $product] = pdpProduct();
    $url = StorefrontUrls::product($product->slug, $product->id);

    $this->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/126.0 Safari/537.36')->get($url)->assertSuccessful();

    expect(app(ProductViewCounter::class)->pending())->toBe([$product->id => 1]);

    $this->actingAs(productAdmin(['admin.product.show']), 'web')
        ->withHeader('User-Agent', 'Mozilla/5.0 (Macintosh) Safari/605.1.15')
        ->get($url.'?preview=1')
        ->assertSuccessful();

    expect(app(ProductViewCounter::class)->pending())->toBe([$product->id => 1]);
});

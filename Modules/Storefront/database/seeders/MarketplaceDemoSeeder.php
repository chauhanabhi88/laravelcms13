<?php

declare(strict_types=1);

namespace Modules\Storefront\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Cart\DTO\CartOwner;
use Modules\Cart\Models\Cart;
use Modules\Cart\Services\CartService;
use Modules\Catalog\Models\CatalogBrand;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Catalog\Services\CategoryAttributeResolver;
use Modules\Catalog\Support\CatalogCache;
use Modules\Core\Support\Money;
use Modules\Customer\Models\Customer;
use Modules\Customer\Models\CustomerAddress;
use Modules\Directory\Models\DirectoryPincode;
use Modules\Logistics\DTO\TrackingEvent;
use Modules\Logistics\Models\Courier;
use Modules\Logistics\Models\Shipment;
use Modules\Logistics\Services\TrackingService;
use Modules\Order\DTO\CheckoutSession;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SalesOrderItem;
use Modules\Order\Models\SellerOrder;
use Modules\Order\Services\CheckoutService;
use Modules\Order\Services\OrderCancellationService;
use Modules\Order\Services\OrderFulfillmentService;
use Modules\Order\Services\PlaceOrderService;
use Modules\Payment\Drivers\FakeGateway;
use Modules\Payment\Services\PaymentService;
use Modules\Product\Database\Seeders\DemoCatalogSeeder;
use Modules\Product\DTO\ImageData;
use Modules\Product\DTO\ProductData;
use Modules\Product\DTO\VariantData;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductImage;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Services\ProductAggregateService;
use Modules\Product\Services\ProductFactoryService;
use Modules\Product\Services\ProductImageService;
use Modules\Product\Services\ProductModerationService;
use Modules\Promotion\Models\Coupon;
use Modules\Promotion\Models\FlashDeal;
use Modules\Promotion\Models\FlashDealItem;
use Modules\Promotion\Services\CouponService;
use Modules\Returns\DTO\ReturnSubmission;
use Modules\Returns\Models\ReturnRequest;
use Modules\Returns\Services\ReturnService;
use Modules\Review\DTO\ReviewSubmission;
use Modules\Review\Models\ProductReview;
use Modules\Review\Services\ReviewModerationService;
use Modules\Review\Services\ReviewService;
use Modules\Seller\Models\Seller;
use Modules\Seller\Models\SellerBankAccount;
use Modules\Seller\Models\SellerUser;
use Modules\Seller\Models\SellerWarehouse;
use Modules\Storefront\Database\Seeders\Demo\DemoData;
use Modules\Storefront\Database\Seeders\Demo\DemoPhotos;
use Modules\Storefront\Services\Search\SearchIndexer;
use Modules\User\Models\User;
use RuntimeException;
use Throwable;

/**
 * A client-ready demo marketplace on top of DemoCatalogSeeder (never in production):
 *
 *     php artisan storefront:seed-demo
 *
 * Real product photos (DemoData::PHOTOS), six suppliers, eight buyers, ~90 orders in every
 * state (delivered with reviews, in transit, failed delivery, RTO, returns, cancellations,
 * waiting for the supplier), coupons, a flash deal, home banners and category tiles, then
 * the finance, report and sitemap jobs. Everything goes through the module services so
 * ledgers, notifications, search and aggregates are real. History is spread over the last
 * six weeks with Carbon's test clock. Re-runnable: finished steps are skipped.
 */
class MarketplaceDemoSeeder extends Seeder
{
    public const ADMIN_EMAIL = 'demo.admin@example.com';

    public const COURIER_CODE = 'demo_express';

    private DemoPhotos $photos;

    private User $admin;

    /** @var array<string, int> attribute code => id */
    private array $attributeIds = [];

    /** @var array<int, array<string, int>> attribute id => option name => option id */
    private array $optionIds = [];

    /** @var array<string, Seller> */
    private array $sellers = [];

    /** @var array<string, int> */
    private array $brands = [];

    /** @var list<Customer> */
    private array $customers = [];

    /** @var array<string, string> settings rows to restore after seeding */
    private array $savedSettings = [];

    private Carbon $today;

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('The demo marketplace is not for production.');

            return;
        }

        $this->photos = new DemoPhotos;
        $this->today = Carbon::now();
        $this->runtimeConfig();

        try {
            $this->step('Base catalog (DemoCatalogSeeder)', fn () => $this->call(DemoCatalogSeeder::class));
            $this->step('Pincodes, states and cities', fn () => $this->directory());
            $this->step('Demo admin, courier and settings', fn () => $this->platform());
            $this->step('Brands', fn () => $this->brands());
            $this->step('Suppliers', fn () => $this->suppliers());
            $this->step('Real photos for the base catalog', fn () => $this->refreshBaseCatalog());
            $this->step('New listings', fn () => $this->listings());
            $this->step('Buyers', fn () => $this->buyers());
            $this->step('Banners, category tiles and home sections', fn () => $this->merchandising());
            $this->step('Coupons and flash deal', fn () => $this->promotions());
            $this->step('Orders, deliveries, returns and reviews', fn () => $this->orders());
            $this->step('Ledger, payouts, reports, search and sitemap', fn () => $this->backOffice());
        } finally {
            $this->travelTo(null);
            $this->restoreSettings();
        }

        $this->command?->info('Demo marketplace ready.');
        $this->command?->line('Admin: '.self::ADMIN_EMAIL.' / '.DemoData::PASSWORD.'   Suppliers: demo.seller1..6@example.com / '.DemoData::PASSWORD);
        $this->command?->line('Buyers: '.implode(', ', array_column(DemoData::CUSTOMERS, 2)).' / '.DemoData::PASSWORD.' (or OTP; codes in storage/logs/sms.log)');
    }

    /**
     * Inline jobs, no real mail / SMS / push, and the offline payment gateway for the
     * seeded prepaid orders (their refunds keep using it later).
     */
    protected function runtimeConfig(): void
    {
        config([
            'queue.default' => 'sync',
            'mail.default' => 'log',
            'core.sms.driver' => 'log',
            'notification.push.driver' => 'log',
            'notification.quiet_hours' => '',
            'payment.default_gateway' => 'fake',
            'logistics.shiprocket.email' => null,
            'logistics.shiprocket.password' => null,
        ]);
    }

    protected function step(string $label, callable $callback): void
    {
        $started = microtime(true);
        $this->command?->getOutput()->write("  {$label} … ");
        $callback();
        $this->command?->getOutput()->writeln(sprintf('<info>done</info> (%.1fs)', microtime(true) - $started));
    }

    // ---------------------------------------------------------------- directory & platform

    protected function directory(): void
    {
        if (! DB::table('directory_country')->where('code', 'IN')->exists()) {
            $countryId = DB::table('directory_country')->insertGetId(['code' => 'IN', 'iso2_code' => 'IN', 'iso3_code' => 'IND', 'is_allowed_country' => 1, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('directory_country_translation')->insert(['directory_country_id' => $countryId, 'name' => 'India', 'locale' => 'en', 'created_at' => now(), 'updated_at' => now()]);
        }

        $csv = storage_path('app/demo-photos/demo-pincodes.csv');
        File::ensureDirectoryExists(dirname($csv));
        $handle = fopen($csv, 'w');
        fputcsv($handle, ['officename', 'pincode', 'district', 'statename', 'latitude', 'longitude']);

        foreach (DemoData::PINCODES as $row) {
            fputcsv($handle, array_slice($row, 0, 6));
        }

        fclose($handle);
        Artisan::call('directory:import-pincodes', ['path' => $csv]);

        foreach (DemoData::PINCODES as $row) {
            DirectoryPincode::query()->where('pincode', $row[1])->update(['zone' => $row[6], 'is_serviceable' => config('core.yes'), 'cod_allowed' => config('core.yes')]);
        }
    }

    protected function platform(): void
    {
        $this->admin = User::query()->where('email', self::ADMIN_EMAIL)->first()
            ?? User::query()->forceCreate([
                'name' => 'Demo Admin',
                'email' => self::ADMIN_EMAIL,
                'password' => bcrypt(DemoData::PASSWORD),
                'role_id' => (int) DB::table('role')->where('slug', 'master_admin')->value('id'),
                'status' => 1,
            ]);

        Courier::query()->firstOrCreate(['code' => self::COURIER_CODE], [
            'name' => 'Demo Express (sandbox)',
            'driver' => 'fake',
            'supports_cod' => config('core.yes'),
            'supports_reverse' => config('core.yes'),
            'priority' => 1,
            'status' => config('core.enabled'),
        ]);

        // Accepting an order books the shipment with the default courier: the sandbox one,
        // so the supplier panel works in the demo without Shiprocket credentials.
        $this->mergeSettings('logistics', ['default_courier' => self::COURIER_CODE, 'default_strategy' => 'recommended', 'live_serviceability' => 0], persist: true);

        // While seeding only: no COD OTP step and no open-COD cap.
        $this->mergeSettings('payment', ['cod_enabled' => 1, 'cod_otp_threshold' => '100000', 'cod_otp_new_customers' => 2, 'cod_daily_limit' => 0]);
        $this->mergeSettings('order', ['max_open_cod_orders' => 0]);
    }

    /**
     * Merge values into a settings row; unless persisted, the old row is restored at the end.
     *
     * @param  array<string, mixed>  $values
     */
    protected function mergeSettings(string $name, array $values, bool $persist = false): void
    {
        $current = DB::table('settings')->where('name', $name)->value('value');

        if (! $persist && ! array_key_exists($name, $this->savedSettings)) {
            $this->savedSettings[$name] = $current ?? '';
        }

        DB::table('settings')->updateOrInsert(
            ['name' => $name],
            ['value' => json_encode(array_merge(json_decode((string) $current, true) ?: [], $values)), 'updated_at' => now(), 'created_at' => now()],
        );
    }

    protected function restoreSettings(): void
    {
        foreach ($this->savedSettings as $name => $value) {
            $value === ''
                ? DB::table('settings')->where('name', $name)->delete()
                : DB::table('settings')->where('name', $name)->update(['value' => $value]);
        }

        $this->savedSettings = [];
    }

    // ---------------------------------------------------------------- catalog

    protected function brands(): void
    {
        foreach (DemoData::BRANDS as $slug => [$name, $verified]) {
            $brand = CatalogBrand::query()->where('slug', $slug)->first()
                ?? CatalogBrand::factory()->create(['slug' => $slug, 'name' => $name, 'is_verified' => $verified ? config('core.yes') : config('core.no')]);
            $this->brands[$slug] = $brand->id;
        }
    }

    protected function suppliers(): void
    {
        foreach (DemoData::EXISTING_SELLER_CITIES as $code => [$city, $state]) {
            $seller = Seller::query()->where('code', $code)->firstOrFail();
            $this->placeSeller($seller, $city, $state);
            $this->sellers[$code] = $seller;
        }

        foreach (DemoData::SELLERS as $position => [$code, $storeName, $email, $pincode, $city, $state, $owner]) {
            $seller = Seller::query()->where('code', $code)->first();

            if ($seller === null) {
                $seller = Seller::factory()->active()->create([
                    'code' => $code,
                    'legal_name' => $storeName.' LLP',
                    'store_name' => $storeName,
                    'store_slug' => Str::slug($storeName),
                    'email' => $email,
                    'phone' => '+91980000000'.($position + 4),
                ]);

                SellerUser::factory()->create([
                    'seller_id' => $seller->id,
                    'name' => $owner,
                    'email' => $email,
                    'phone' => '+91981000000'.($position + 4),
                    'password' => DemoData::PASSWORD,
                ]);

                SellerWarehouse::factory()->create(['seller_id' => $seller->id, 'pincode' => $pincode]);
            }

            $this->placeSeller($seller, $city, $state);
            $this->sellers[$code] = $seller;
        }
    }

    /**
     * The seller's warehouse address and state come from the pincode master; the primary
     * bank account is verified so payouts can be built.
     */
    protected function placeSeller(Seller $seller, string $city, string $state): void
    {
        $warehouse = SellerWarehouse::query()->where('seller_id', $seller->id)->orderBy('id')->firstOrFail();
        $pincode = DirectoryPincode::query()->where('pincode', $warehouse->pincode)->orderBy('id')->first();

        $warehouse->forceFill([
            'name' => 'Main warehouse',
            'address_line1' => 'Unit 4, '.$city.' Industrial Area',
            'city_name' => $city,
            'state_name' => $state,
            'state_id' => $pincode?->state_id,
            'city_id' => $pincode?->city_id,
        ])->save();

        $seller->forceFill(['state_id' => $pincode?->state_id])->save();

        if (! SellerBankAccount::query()->where('seller_id', $seller->id)->exists()) {
            SellerBankAccount::factory()->verified()->create(['seller_id' => $seller->id, 'account_holder_name' => $seller->legal_name, 'is_primary' => config('core.yes')]);
        }
    }

    protected function refreshBaseCatalog(): void
    {
        foreach (DemoData::EXISTING as $sku => $change) {
            $variant = ProductVariant::query()->where('seller_sku', $sku)->first();
            $product = $variant !== null ? Product::query()->find($variant->product_id) : null;

            if ($product === null) {
                continue;
            }

            if ($change['pause'] ?? false) {
                if ($product->status->value === 'live') {
                    app(ProductModerationService::class)->pause($product);
                }

                continue;
            }

            $isPlaceholder = ProductImage::query()->where('product_id', $product->id)->where('alt', 'like', '% - Front view')->exists();

            if ($isPlaceholder) {
                ProductImage::query()->where('product_id', $product->id)->delete();
                $position = 0;

                foreach ($change['photos'] as $key) {
                    foreach ($this->photos->files($key) as $file) {
                        $image = $this->storeImage($file, $change['name'].($position > 0 ? ' - view '.($position + 1) : ''));
                        ProductImage::query()->create([
                            'product_id' => $product->id,
                            'path' => $image->path,
                            'position' => $position++,
                            'width' => $image->width,
                            'height' => $image->height,
                            'content_hash' => $image->contentHash,
                            'alt' => $image->alt,
                        ]);
                    }
                }
            }

            DB::table('product_translation')->where('product_id', $product->id)->update(['name' => $change['name']]);
            $product->forceFill(['slug' => Str::slug($change['name']), 'catalog_brand_id' => $this->brands[$change['brand']] ?? null])->save();

            app(ProductAggregateService::class)->refresh($product->id);
            app(SearchIndexer::class)->index($product->id);
        }
    }

    protected function listings(): void
    {
        $factory = app(ProductFactoryService::class);
        $moderation = app(ProductModerationService::class);
        $categories = CatalogCategory::query()->pluck('id', 'slug');
        $count = count(DemoData::PRODUCTS);

        foreach (DemoData::PRODUCTS as $index => [$key, $sellerCode, $brand, $categorySlug, $name, $price, $mrp, $colour, $sizes, $stock, $photoKeys, $highlights, $description, $extra]) {
            $sku = 'DM-'.Str::upper($key);
            $seller = $this->sellers[$sellerCode];

            if (ProductVariant::query()->where('seller_id', $seller->id)->where('seller_sku', 'like', $sku.'%')->exists()) {
                continue;
            }

            $categoryId = (int) ($categories[$categorySlug] ?? throw new RuntimeException("Unknown category [{$categorySlug}]."));
            $axes = app(CategoryAttributeResolver::class)->variantAxes($categoryId)->keyBy('code');
            $sizeAxis = $axes->get('size');
            $colourAxis = $axes->get('colour');

            if ($sizeAxis !== null && $sizes === []) {
                $sizes = ['Free Size'];
            }

            $variants = [];
            foreach ($sizes === [] ? [null] : $sizes as $position => $size) {
                $options = [];
                if ($colourAxis !== null) {
                    $options[$colourAxis->attributeId] = $this->option('colour', $colour);
                }
                if ($size !== null && $sizeAxis !== null) {
                    $options[$sizeAxis->attributeId] = $this->option('size', $size);
                }

                $variants[] = new VariantData(
                    sellerSku: $sku.($size !== null ? '-'.Str::upper(Str::slug($size)) : ''),
                    mrp: Money::of($mrp),
                    price: Money::of($price),
                    stock: max(3, $stock - $position * 3),
                    options: $options,
                    weightGrams: 450,
                    lengthCm: '30',
                    breadthCm: '24',
                    heightCm: '6',
                );
            }

            $images = [];
            foreach ($photoKeys as $photoKey) {
                foreach ($this->photos->files($photoKey) as $file) {
                    $images[] = $this->storeImage($file, $name.(count($images) > 0 ? ' - view '.(count($images) + 1) : ''));
                }
            }

            // Specifications only where the category maps the attribute.
            $applicable = app(CategoryAttributeResolver::class)->for($categoryId)->filter(fn ($attribute): bool => ! $attribute->isVariantAxis)->keyBy('code');
            $attributes = [];
            foreach ($extra as $code => $value) {
                if ($applicable->has($code)) {
                    $attributes[$applicable->get($code)->attributeId] = $this->option($code, $value);
                }
            }

            // Listed 60 to 46 days ago (before the first order); later rows are newer arrivals.
            $this->travelTo($this->today->copy()->subDays(60)->addHours((int) round($index * (14 * 24) / $count)));

            $product = $factory->create(new ProductData(
                sellerId: $seller->id,
                categoryId: $categoryId,
                translations: ['en' => ['name' => $name, 'description' => $description, 'highlights' => $highlights]],
                variants: $variants,
                images: $images,
                attributes: $attributes,
                brandId: $this->brands[$brand] ?? null,
                manufacturerDetails: ['name' => $seller->legal_name, 'address' => $seller->store_name.', India'],
                isCodAllowed: Money::of($price)->compare(Money::of('5000')) <= 0,
            ));

            $moderation->submit($product);
            $moderation->approve($product, $this->admin);
        }

        $this->travelTo(null);
    }

    protected function storeImage(string $file, string $alt): ImageData
    {
        $shot = $this->photos->productShot($file);
        $extension = strtolower(pathinfo($shot, PATHINFO_EXTENSION)) ?: 'jpg';

        return app(ProductImageService::class)->store(new UploadedFile($shot, 'photo.'.$extension, null, null, true), null, $alt);
    }

    /**
     * @return list<int> the attribute id (empty when the code does not exist)
     */
    protected function attributeId(string $code): array
    {
        $this->attributeIds[$code] ??= (int) DB::table('attribute')->where('code', $code)->value('id');

        return $this->attributeIds[$code] > 0 ? [$this->attributeIds[$code]] : [];
    }

    protected function option(string $code, string $name): int
    {
        $attributeId = $this->attributeId($code)[0] ?? throw new RuntimeException("Unknown attribute [{$code}].");

        $this->optionIds[$attributeId] ??= DB::table('attribute_option as o')
            ->join('attribute_option_translation as t', 't.attribute_option_id', '=', 'o.id')
            ->where('o.attribute_id', $attributeId)
            ->where('t.locale', 'en')
            ->pluck('o.id', 't.name')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return $this->optionIds[$attributeId][$name] ?? throw new RuntimeException("Unknown {$code} option [{$name}].");
    }

    // ---------------------------------------------------------------- buyers

    protected function buyers(): void
    {
        foreach (DemoData::CUSTOMERS as $index => [$first, $last, $email, $phone, $pincode, $line1, $line2, $business]) {
            $customer = Customer::query()->where('email', $email)->orWhere('phone', $phone)->first();

            if ($customer === null) {
                $factory = Customer::factory()->withPhone($phone);
                $customer = ($business !== null ? $factory->reseller($business) : $factory)->create([
                    'first_name' => $first,
                    'last_name' => $last,
                    'email' => $email,
                    'password' => Hash::make(DemoData::PASSWORD),
                    'contact_number' => substr($phone, 3),
                    'created_at' => $this->today->copy()->subDays(60 - $index * 3),
                ]);
            }

            if (! CustomerAddress::query()->where('customer_id', $customer->id)->exists()) {
                $this->address($customer, $pincode, $line1, $line2, 'home', true);

                if ($index === 0) {
                    $this->address($customer, '560034', 'Tower B, 5th Floor, Tech Park', 'Koramangala 5th Block', 'work', false);
                }
            }

            $this->customers[$index] = $customer->fresh();
        }
    }

    protected function address(Customer $customer, string $pincode, string $line1, string $line2, string $tag, bool $default): void
    {
        $master = DirectoryPincode::query()->where('pincode', $pincode)->orderBy('id')->firstOrFail();

        CustomerAddress::factory()->create([
            'customer_id' => $customer->id,
            'name' => trim($customer->first_name.' '.$customer->last_name),
            'phone' => $customer->phone,
            'address_line1' => $line1,
            'address_line2' => $line2,
            'pincode' => $pincode,
            'city_name' => DB::table('directory_country_city_translation')->where('directory_country_city_id', $master->city_id)->value('name') ?? $master->district,
            'state_id' => $master->state_id,
            'state_name' => null,
            'tag' => $tag,
            'is_default_address' => config('customer.is_default_address.'.($default ? 'yes' : 'no')),
        ]);
    }

    // ---------------------------------------------------------------- merchandising

    protected function merchandising(): void
    {
        $this->categoryTiles();
        $this->banners();
        $this->homeSections();
        app(CatalogCache::class)->flush();
    }

    protected function categoryTiles(): void
    {
        $tiles = [
            'ethnic-wear' => 'ov:saree-pink-kalamkari', 'western-wear' => 'dj163', 'men' => 'dj83', 'kids-baby' => 'dj162',
            'home-living' => 'dj45', 'beauty-care' => 'dj4', 'jewellery-accessories' => 'ov:jhumka', 'bags-footwear' => 'dj172',
            'gadgets-electronics' => 'ov:speaker',
            'sarees' => 'ov:saree-colour-block', 'lehengas' => 'ov:lehenga-blue-brocade', 'dresses' => 'dj164', 'shirts' => 'dj85',
            'kitchen-tools' => 'dj51', 'watches' => 'dj193', 'sunglasses' => 'dj155', 'women-footwear' => 'dj189', 'handbags' => 'dj176',
        ];

        foreach ($tiles as $slug => $photoKey) {
            $category = CatalogCategory::query()->where('slug', $slug)->first();

            if ($category === null || $category->image) {
                continue;
            }

            $name = Str::random(40).'.jpg';
            Storage::disk('public')->put(config('catalog.upload.category').'/'.$name, (string) file_get_contents($this->photos->tile($this->photos->files($photoKey)[0])));
            $category->forceFill(['image' => $name])->save();
        }
    }

    protected function banners(): void
    {
        $category = fn (string $slug): string => '/c/'.$slug;

        $groups = [
            'home-banner' => [
                ['demo-hero-ethnic', 'The Festive Ethnic Edit', 'Sarees and lehengas from ₹749. Pay on delivery, free shipping.', $category('ethnic-wear'), ['ov:saree-pink-kalamkari', 'ov:lehenga-ombre', 'ov:lehenga-blue-brocade'], [[19, 78, 74], [240, 253, 250]]],
                ['demo-hero-kitchen', 'Kitchen Upgrade Sale', 'Up to 50% off cookware, tools and appliances.', $category('home-living'), ['dj51', 'dj71', 'dj76'], [[120, 53, 15], [255, 247, 237]]],
                ['demo-hero-accessories', 'Accessorise for Less', 'Watches, sunglasses and jewellery under ₹1,599.', $category('jewellery-accessories'), ['dj193', 'dj155', 'ov:jhumka'], [[30, 41, 59], [241, 245, 249]]],
            ],
            'home-promo' => [
                ['demo-promo-western', 'New in Western Wear', 'Dresses from ₹649', $category('western-wear'), ['dj163', 'dj180'], [[131, 24, 67], [253, 242, 248]]],
                ['demo-promo-men', 'Shirts for Every Day', 'Checks, flannels and prints', $category('men'), ['dj83', 'dj86'], [[30, 58, 138], [239, 246, 255]]],
                ['demo-promo-home', 'Make Home Cosy', 'Bedsheets, curtains and decor', $category('home-living'), ['ov:bedsheet-floral', 'dj47'], [[20, 83, 45], [240, 253, 244]]],
            ],
        ];

        foreach ($groups as $groupCode => $banners) {
            $groupId = DB::table('banner_group')->where('code', $groupCode)->value('id')
                ?? DB::table('banner_group')->insertGetId(['code' => $groupCode, 'status' => 1, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()]);

            if (DB::table('banner')->where('code', $banners[0][0])->exists()) {
                continue;
            }

            // The demo banners replace whatever the group showed before.
            DB::table('banner')->where('group_id', $groupId)->update(['status' => 2]);

            foreach ($banners as $position => [$code, $title, $content, $url, $photoKeys, $colours]) {
                $files = array_map(fn (string $key): string => $this->photos->files($key)[0], $photoKeys);
                $file = $groupCode === 'home-banner'
                    ? $this->photos->banner($code, $files, $colours)
                    : $this->photos->banner($code, $files, $colours, 1200, 600);
                $name = Str::random(40).'.jpg';
                Storage::disk('public')->put('banner/'.$name, (string) file_get_contents($file));

                $bannerId = DB::table('banner')->insertGetId([
                    'group_id' => $groupId, 'image' => $name, 'code' => $code, 'url' => $url,
                    'is_featured' => 2, 'sort_order' => $position + 1, 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
                ]);

                foreach (['en', 'hi'] as $locale) {
                    DB::table('banner_translation')->insert(['banner_id' => $bannerId, 'title' => $title, 'content' => $content, 'locale' => $locale, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
    }

    protected function homeSections(): void
    {
        $categoryId = fn (string $slug): int => (int) CatalogCategory::query()->where('slug', $slug)->value('id');

        $collections = [
            ['ethnic-edit', 'The Ethnic Edit', 'Sarees, lehengas and dupattas loved by our buyers', ['category_ids' => [$categoryId('ethnic-wear')]], 'popularity'],
            ['kitchen-essentials', 'Kitchen Essentials', 'Everyday tools and appliances at the best prices', ['category_ids' => [$categoryId('kitchen-tools')]], 'discount'],
        ];

        foreach ($collections as $position => [$slug, $title, $subtitle, $rules, $sort]) {
            if (DB::table('product_collection')->where('slug', $slug)->exists()) {
                continue;
            }

            $collectionId = DB::table('product_collection')->insertGetId(['slug' => $slug, 'type' => 'rule', 'rules' => json_encode($rules), 'product_ids' => null, 'sort' => $sort, 'status' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $this->translations('product_collection_translation', 'product_collection_id', $collectionId, ['name' => $title, 'description' => $subtitle]);
            $this->section('collection', ['collection_id' => $collectionId, 'limit' => 12], 52 + $position * 4, $title, $subtitle);
        }

        if (! DB::table('home_section')->where('type', 'flash_deal')->exists()) {
            $this->section('flash_deal', ['limit' => 12], 35, 'Flash Deals', 'Prices drop for a few days only');
        }

        // The legacy CMS intro block and blog teasers do not belong on a shop front.
        DB::table('home_section')->whereIn('type', ['html_block', 'blog_posts'])->update(['status' => 2]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function section(string $type, array $config, int $sortOrder, string $title, ?string $subtitle): void
    {
        $id = DB::table('home_section')->insertGetId(['type' => $type, 'config' => json_encode($config), 'sort_order' => $sortOrder, 'status' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->translations('home_section_translation', 'home_section_id', $id, ['title' => $title, 'subtitle' => $subtitle]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    protected function translations(string $table, string $foreignKey, int $id, array $values): void
    {
        foreach (['en', 'hi'] as $locale) {
            DB::table($table)->insert([$foreignKey => $id, 'locale' => $locale, 'created_at' => now(), 'updated_at' => now()] + $values);
        }
    }

    // ---------------------------------------------------------------- promotions

    protected function promotions(): void
    {
        $ethnicId = (int) CatalogCategory::query()->where('slug', 'ethnic-wear')->value('id');

        $coupons = [
            ['WELCOME100', 'Welcome offer', '₹100 off your first order above ₹499', fn () => Coupon::factory()->flat('100')->firstOrderOnly(), ['min_cart_value' => '499']],
            ['FESTIVE20', 'Festive sale', '20% off up to ₹300 on orders above ₹999', fn () => Coupon::factory()->percent('20', '300'), ['min_cart_value' => '999', 'usage_limit_per_customer' => 3]],
            ['ETHNIC15', 'Ethnic week', '15% off sarees, lehengas and dupattas', fn () => Coupon::factory()->percent('15', '500')->scoped(Coupon::SCOPE_CATEGORY, [$ethnicId]), ['min_cart_value' => '699']],
            ['RASOI50', 'RasoiCraft special', '₹50 off kitchen tools from RasoiCraft above ₹399', fn () => Coupon::factory()->flat('50')->forSeller($this->sellers['DEMO04']), ['min_cart_value' => '399']],
            ['BIGSAVE', 'Big basket saver', 'Flat ₹75 off automatically on orders above ₹1,499', fn () => Coupon::factory()->flat('75')->automatic(), ['min_cart_value' => '1499', 'usage_limit_per_customer' => null]],
        ];

        foreach ($coupons as [$code, $name, $description, $factory, $attributes]) {
            if (Coupon::query()->where('code', $code)->exists()) {
                continue;
            }

            $factory()->create($attributes + [
                'code' => $code, 'name' => $name, 'description' => $description,
                'starts_at' => $this->today->copy()->subDays(50), 'ends_at' => $this->today->copy()->addMonths(3),
                'created_by' => $this->admin->id,
            ]);
        }

        if (FlashDeal::query()->where('name', 'Weekend Flash Sale')->exists()) {
            return;
        }

        $deal = FlashDeal::factory()->create([
            'name' => 'Weekend Flash Sale',
            'starts_at' => $this->today->copy()->subHours(6),
            'ends_at' => $this->today->copy()->addDays(3)->endOfDay(),
            'banner' => null,
            'status' => config('core.enabled'),
        ]);

        foreach (['blender' => '1899', 'watch-steel' => '999', 'sunglasses-retro' => '399', 'shirt-gingham' => '479', 'plant-areca' => '749', 'chakla-belan' => '279', 'bag-navy-tote' => '749', 'DEMO-024' => '999'] as $key => $dealPrice) {
            FlashDealItem::factory()->forVariant($this->variantFor($key))->create([
                'flash_deal_id' => $deal->id,
                'deal_price' => $dealPrice,
                'stock_cap' => 25,
            ]);
        }
    }

    // ---------------------------------------------------------------- orders

    /**
     * [customer index, product keys (DemoData key or base SKU), quantity, method, days ago,
     * outcome, review rating, coupon]. Outcomes: review, delivered, returned, return_open,
     * shipped, ofd, ndr, rto, ready, pending, cancelled.
     *
     * @return list<array{0: int, 1: list<string>, 2: int, 3: string, 4: int, 5: string, 6: int|null, 7: string|null}>
     */
    protected function scenarios(): array
    {
        $cod = CheckoutSession::METHOD_COD;
        $pre = CheckoutSession::METHOD_PREPAID;

        return [
            [1, ['saree-colour-block'], 1, $cod, 41, 'review', 5, 'WELCOME100'],
            [2, ['shirt-gingham'], 2, $pre, 40, 'review', 4, null],
            [3, ['chakla-belan', 'spatula'], 1, $cod, 39, 'review', 5, 'WELCOME100'],
            [4, ['DEMO-001'], 1, $pre, 38, 'review', 5, null],
            [5, ['dress-leaf-maxi'], 1, $cod, 37, 'review', 4, null],
            [6, ['blender'], 1, $pre, 36, 'review', 5, 'FESTIVE20'],
            [7, ['heels-black'], 1, $cod, 35, 'review', 3, null],
            [1, ['lehenga-blue'], 1, $pre, 34, 'review', 5, 'ETHNIC15'],
            [2, ['watch-steel'], 1, $cod, 33, 'review', 4, null],
            [3, ['DEMO-025'], 1, $cod, 32, 'review', 5, null],
            [4, ['earring-hoop', 'earring-leaf'], 1, $cod, 31, 'review', 4, null],
            [5, ['DEMO-019'], 1, $pre, 30, 'review', 5, null],
            [6, ['spice-rack'], 1, $cod, 29, 'review', 4, 'RASOI50'],
            [7, ['sunglasses-aviator'], 1, $cod, 28, 'review', 5, null],
            [1, ['DEMO-024'], 1, $pre, 27, 'review', 4, null],
            [2, ['frock-blue'], 1, $cod, 26, 'review', 5, null],
            [3, ['lipstick', 'nail-polish'], 2, $cod, 25, 'review', 4, null],
            [4, ['DEMO-022'], 1, $cod, 24, 'review', 5, null],
            [5, ['saree-orange-stripe'], 1, $cod, 23, 'review', 3, null],
            [6, ['plant-pot'], 2, $pre, 22, 'review', 5, null],
            [7, ['DEMO-011'], 1, $cod, 21, 'review', 4, null],
            [0, ['DEMO-003'], 1, $cod, 20, 'review', 5, 'WELCOME100'],
            [1, ['bag-navy-tote'], 1, $cod, 19, 'review', 4, null],
            [2, ['shirt-buffalo', 'shirt-tropical'], 1, $pre, 18, 'review', 5, 'FESTIVE20'],
            [3, ['induction'], 1, $pre, 17, 'review', 4, null],
            [4, ['DEMO-028'], 1, $cod, 16, 'review', 5, null],
            [5, ['table-lamp'], 1, $cod, 15, 'review', 4, null],
            [6, ['watch-gold'], 1, $pre, 14, 'review', 5, null],
            [7, ['DEMO-008'], 1, $pre, 13, 'review', 5, 'ETHNIC15'],
            [1, ['lehenga-green'], 1, $cod, 12, 'review', 4, null],
            [2, ['dress-polka'], 1, $cod, 12, 'review', 5, null],
            [3, ['DEMO-016'], 1, $cod, 11, 'review', 4, null],
            [5, ['chopping-board', 'chef-knife'], 1, $pre, 11, 'review', 5, null],
            [6, ['DEMO-010'], 1, $cod, 10, 'review', 3, null],
            [7, ['saree-pink-check'], 1, $cod, 10, 'review', 5, null],
            [4, ['bag-white-backpack'], 1, $cod, 9, 'review', 4, null],
            [2, ['heels-red'], 1, $pre, 9, 'review', 5, null],
            [3, ['lehenga-ombre'], 1, $pre, 8, 'review', 5, null],
            [1, ['DEMO-029', 'DEMO-028'], 1, $cod, 8, 'review', 4, null],
            [5, ['stock-pot'], 1, $cod, 7, 'review', 4, null],
            [6, ['DEMO-015'], 1, $cod, 7, 'review', 4, null],
            [7, ['frock-tartan'], 1, $cod, 7, 'review', 5, null],
            [0, ['DEMO-025'], 1, $pre, 7, 'review', 4, null],
            [1, ['chef-knife'], 2, $cod, 6, 'delivered', null, null],
            [4, ['earphones'], 1, $cod, 6, 'delivered', null, null],
            [0, ['heels-red'], 1, $cod, 5, 'delivered', null, null],
            [0, ['DEMO-016'], 1, $cod, 6, 'returned', null, null],
            [2, ['dress-mint-tiered'], 1, $pre, 6, 'returned', null, null],
            [3, ['dress-grey-midi'], 1, $cod, 5, 'return_open', null, null],
            [5, ['watch-steel'], 1, $cod, 5, 'return_open', null, null],
            [0, ['lehenga-green'], 1, $pre, 2, 'shipped', null, null],
            [6, ['wok'], 1, $cod, 2, 'shipped', null, null],
            [3, ['blender'], 1, $cod, 2, 'ofd', null, null],
            [0, ['DEMO-020'], 1, $cod, 3, 'ndr', null, null],
            [7, ['table-lamp'], 1, $cod, 4, 'ndr', null, null],
            [4, ['stock-pot'], 1, $cod, 8, 'rto', null, null],
            [5, ['DEMO-030'], 1, $cod, 1, 'ready', null, null],
            [6, ['mug-tree', 'serving-tray'], 1, $cod, 1, 'ready', null, null],
            [7, ['compact'], 2, $cod, 0, 'pending', null, null],
            [1, ['earring-emerald'], 1, $cod, 0, 'pending', null, null],
            // The demo buyer keeps one open COD order (the failed delivery), well under the
            // "max open COD orders" rule, so live COD checkouts work during a demo.
            [6, ['saree-pink-check'], 1, $cod, 0, 'pending', null, null],
            [2, ['microwave'], 1, $pre, 0, 'pending', null, null],
            [4, ['shirt-gingham', 'dress-leaf-maxi'], 1, $cod, 0, 'pending', null, null],
            [3, ['serving-tray'], 1, $cod, 5, 'cancelled', null, null],
            [5, ['dress-mint-tiered'], 1, $cod, 3, 'cancelled', null, null],
        ];
    }

    protected function orders(): void
    {
        $demoCustomerIds = array_map(fn (Customer $customer): int => $customer->id, $this->customers);

        if (SalesOrder::query()->whereIn('customer_id', $demoCustomerIds)->exists()) {
            $this->command?->getOutput()->write('(orders exist, skipped) ');

            return;
        }

        foreach ($this->scenarios() as $index => [$customerIndex, $keys, $quantity, $method, $daysAgo, $outcome, $rating, $coupon]) {
            $customer = $this->customers[$customerIndex];
            $placedAt = $this->today->copy()->subDays($daysAgo)->setTime(9, 0)->addMinutes(($index * 37) % 600);

            if ($daysAgo === 0) {
                $placedAt = $this->today->copy()->subMinutes(30 + $index * 7);
            }

            try {
                $order = $this->place($customer, $keys, $quantity, $method, $placedAt, $coupon);
                $this->play($order, $customer, $outcome, $placedAt, $rating, $index);
            } catch (Throwable $e) {
                $this->command?->getOutput()->writeln('');
                $this->command?->warn("  Scenario #{$index} ({$outcome}, ".implode('+', $keys)."): {$e->getMessage()}");
            } finally {
                $this->travelTo(null);
            }
        }
    }

    /**
     * @param  list<string>  $keys
     */
    protected function place(Customer $customer, array $keys, int $quantity, string $method, Carbon $at, ?string $coupon): SalesOrder
    {
        $this->travelTo($at);
        $owner = new CartOwner($customer, null);
        $cartService = app(CartService::class);

        foreach ($keys as $key) {
            $cartService->add($owner, $this->variantFor($key)->id, $quantity);
        }

        if ($coupon !== null) {
            $cart = Cart::query()->where('customer_id', $customer->id)->where('type', Cart::TYPE_STANDARD)->firstOrFail();
            app(CouponService::class)->apply($cart, $coupon, $method);
        }

        $address = app(CheckoutService::class)->addresses($customer)->first();
        $session = CheckoutSession::fromArray([
            'cart_type' => 'standard',
            'address_id' => $address?->id,
            'payment_method' => $method,
            'token' => CheckoutSession::newToken(),
        ]);

        $result = app(PlaceOrderService::class)->place($customer, $session, ['ip' => '127.0.0.1', 'channel' => 'web']);

        if ($method === CheckoutSession::METHOD_PREPAID) {
            $this->travelTo($at->copy()->addMinutes(2));
            $gateway = app(FakeGateway::class);
            app(PaymentService::class)->markCaptured($result->payment, $gateway->fetch($gateway->simulate($result->payment->gateway_order_id)['razorpay_payment_id']), 'webhook');
        }

        return $result->order->refresh();
    }

    protected function variantFor(string $key): ProductVariant
    {
        $sku = str_starts_with($key, 'DEMO-') ? $key : 'DM-'.Str::upper($key);

        return ProductVariant::query()->where('seller_sku', $sku)->first()
            ?? ProductVariant::query()->where('seller_sku', 'like', $sku.'-%')->orderBy('id')->skip(1)->first()
            ?? ProductVariant::query()->where('seller_sku', 'like', $sku.'%')->orderBy('id')->firstOrFail();
    }

    protected function play(SalesOrder $order, Customer $customer, string $outcome, Carbon $placedAt, ?int $rating, int $index): void
    {
        $sellerOrders = SellerOrder::query()->where('sales_order_id', $order->id)->orderBy('id')->get();

        if ($outcome === 'pending') {
            return;
        }

        if ($outcome === 'cancelled') {
            $this->travelTo($placedAt->copy()->addHours(2));
            app(OrderCancellationService::class)->cancelForCustomer($order->refresh(), null, 'Ordered by mistake', $customer);

            return;
        }

        foreach ($sellerOrders as $sellerOrder) {
            $this->travelTo($placedAt->copy()->addHours(3));
            app(OrderFulfillmentService::class)->accept($sellerOrder->refresh());
        }

        if ($outcome === 'ready') {
            return;
        }

        $tracks = [
            'shipped' => [['picked_up', 20], ['in_transit', 28]],
            'ofd' => [['picked_up', 20], ['in_transit', 28], ['out_for_delivery', 44]],
            'ndr' => [['picked_up', 20], ['in_transit', 28], ['out_for_delivery', 56], ['ndr', 62]],
            'rto' => [['picked_up', 20], ['in_transit', 28], ['out_for_delivery', 56], ['ndr', 62], ['rto_initiated', 82], ['rto_in_transit', 98], ['rto_delivered', 140]],
        ][$outcome] ?? [['picked_up', 20], ['in_transit', 28], ['out_for_delivery', 70], ['delivered', 76]];

        foreach ($sellerOrders as $sellerOrder) {
            $this->scan($sellerOrder, $placedAt, $tracks);
        }

        if ($outcome === 'review') {
            $this->travelTo($placedAt->copy()->addHours(100));

            foreach (SalesOrderItem::query()->where('sales_order_id', $order->id)->get() as $position => $item) {
                $this->review($customer, $item, max(3, (int) $rating - ($position % 2)), $index + $position);
            }
        }

        if (in_array($outcome, ['returned', 'return_open'], true)) {
            $this->travelTo($placedAt->copy()->addHours(80));
            $return = $this->raiseReturn($sellerOrders->first()->refresh(), $customer, $order);

            if ($outcome === 'returned') {
                $this->returnFlow($return, $placedAt);
            }
        }
    }

    /**
     * @param  list<array{0: string, 1: int}>  $tracks  [mapped status, hours after placement]
     */
    protected function scan(SellerOrder $sellerOrder, Carbon $placedAt, array $tracks): void
    {
        $shipment = Shipment::query()->forward()->where('seller_order_id', $sellerOrder->id)->where('status', '!=', Shipment::STATUS_CANCELLED)->firstOrFail();
        $cities = ['Origin hub', 'Sorting centre', 'Destination hub', 'Delivery centre'];

        foreach ($tracks as $position => [$status, $hours]) {
            $at = $placedAt->copy()->addHours($hours);
            $at = $at->greaterThan($this->today) ? $this->today->copy()->subMinutes(20 - $position) : $at;
            $this->travelTo($at);
            $label = $status === 'ndr' ? 'Customer not available' : strtoupper(str_replace('_', ' ', $status));
            app(TrackingService::class)->apply($shipment->refresh(), [new TrackingEvent($status, $label, $at, '', $cities[min($position, 3)], $status === 'ndr' ? 'Customer not available at the address' : null)]);
        }
    }

    protected function review(Customer $customer, SalesOrderItem $item, int $rating, int $seed): void
    {
        $texts = DemoData::REVIEWS[$rating] ?? DemoData::REVIEWS[4];
        [$title, $body] = $texts[$seed % count($texts)];
        $photos = [];

        // Every fourth review carries the buyer's photo (a copy of the listing photo).
        if ($seed % 4 === 0 && ($path = ProductImage::query()->where('product_id', $item->product_id)->orderBy('position')->value('path'))) {
            $source = Storage::disk('public')->path(config('product.upload.folder', 'product').'/'.$path);

            if (is_file($source)) {
                $copy = storage_path('app/demo-photos/work/review-'.$item->id.'.jpg');
                File::copy($source, $copy);
                $photos[] = new UploadedFile($copy, 'photo.jpg', 'image/jpeg', null, true);
            }
        }

        $review = app(ReviewService::class)->create($customer, $item->fresh(['salesOrder', 'sellerOrder']), new ReviewSubmission($rating, $title, $body, $photos));

        if ($review->status !== ProductReview::STATUS_PUBLISHED) {
            app(ReviewModerationService::class)->publish($review, $this->admin);
        }
    }

    protected function raiseReturn(SellerOrder $sellerOrder, Customer $customer, SalesOrder $order): ReturnRequest
    {
        $items = SalesOrderItem::query()->where('seller_order_id', $sellerOrder->id)->get()
            ->mapWithKeys(fn (SalesOrderItem $item): array => [$item->id => ['quantity' => 1, 'exchange_variant_id' => null]])
            ->all();
        $isCod = $order->payment_method === CheckoutSession::METHOD_COD;

        return app(ReturnService::class)->create($customer, new ReturnSubmission(
            $sellerOrder->id,
            ReturnRequest::TYPE_RETURN,
            'wrong_size',
            'The fit is a little tight. I would like a refund.',
            $items,
            [],
            $isCod ? 'upi' : null,
            $isCod ? ['vpa' => Str::lower($customer->first_name).'@okbank'] : null,
            null,
        ));
    }

    /**
     * Approve → reverse pickup → received → QC passed (the refund follows).
     */
    protected function returnFlow(ReturnRequest $return, Carbon $placedAt): void
    {
        $service = app(ReturnService::class);
        $return->refresh();

        if ($return->status === ReturnRequest::STATUS_REQUESTED) {
            $service->approve($return, $this->admin);
        }

        if ($return->refresh()->reverse_shipment_id === null) {
            $service->schedulePickup($return->refresh(), $this->admin);
        }

        $shipment = Shipment::query()->findOrFail($return->refresh()->reverse_shipment_id);

        foreach ([['picked_up', 100], ['in_transit', 110], ['delivered', 124]] as [$status, $hours]) {
            $at = $placedAt->copy()->addHours($hours);
            $at = $at->greaterThan($this->today) ? $this->today->copy()->subMinutes(10) : $at;
            $this->travelTo($at);
            app(TrackingService::class)->apply($shipment->refresh(), [new TrackingEvent($status, strtoupper(str_replace('_', ' ', $status)), $at, '', 'Return hub', null)]);
        }

        if ($return->refresh()->status === ReturnRequest::STATUS_RECEIVED) {
            $service->qcPass($return, 'Item received unused with tags.', $this->admin);
        }
    }

    // ---------------------------------------------------------------- back office

    protected function backOffice(): void
    {
        $this->travelTo(null);

        // The sandbox driver names its carriers "Fake …"; buyers see the carrier on their orders.
        DB::table('shipment')->where('carrier_name', 'like', 'Fake%')->update(['carrier_name' => 'Demo Express']);

        foreach ([
            ['finance:release-eligible', []],
            ['finance:build-payouts', ['--force' => true]],
            ['report:rebuild', []],
            ['storefront:reindex', []],
            ['storefront:sitemap', []],
        ] as [$command, $arguments]) {
            try {
                Artisan::call($command, $arguments);
            } catch (Throwable $e) {
                $this->command?->warn("  {$command}: {$e->getMessage()}");
            }
        }

        app(CatalogCache::class)->flush();
        Artisan::call('cache:clear');
    }

    protected function travelTo(?Carbon $at): void
    {
        Carbon::setTestNow($at);
        CarbonImmutable::setTestNow($at?->toImmutable());
    }
}

<?php

declare(strict_types=1);

namespace Modules\Product\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Customer\Models\Customer;
use Modules\Directory\Models\DirectoryPincode;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductImage;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Services\ProductAggregateService;
use Modules\Product\Services\ProductImageService;
use Modules\Seller\Models\Seller;
use Modules\Seller\Models\SellerUser;
use Modules\Seller\Models\SellerWarehouse;

/**
 * Demo catalog for trying the storefront by hand (never in production):
 *
 *     php artisan db:seed --class="Modules\Product\Database\Seeders\DemoCatalogSeeder"
 *
 * Creates COD-enabled pincodes, three active suppliers (owner logins, password Secret#123),
 * a demo buyer and 30 live products with generated images in the seeded taxonomy (run
 * `php artisan module:seed Catalog` first). Runs once: it stops if the demo suppliers exist.
 */
class DemoCatalogSeeder extends Seeder
{
    public const PASSWORD = 'Secret#123';

    public const BUYER_EMAIL = 'demo.buyer@example.com';

    public const BUYER_PHONE = '+919876500001';

    /**
     * [pincode, office, district].
     */
    private const PINCODES = [
        ['560001', 'Bengaluru GPO', 'Bengaluru Urban'],
        ['560034', 'Koramangala SO', 'Bengaluru Urban'],
        ['110001', 'New Delhi GPO', 'New Delhi'],
        ['400001', 'Mumbai GPO', 'Mumbai'],
        ['600001', 'Chennai GPO', 'Chennai'],
        ['700001', 'Kolkata GPO', 'Kolkata'],
    ];

    /**
     * [code, store name, owner email, warehouse pincode].
     */
    private const SELLERS = [
        ['DEMO01', 'Shree Ethnic House', 'demo.seller1@example.com', '560034'],
        ['DEMO02', 'Urban Thread Co', 'demo.seller2@example.com', '110001'],
        ['DEMO03', 'Gharelu Living & Gadgets', 'demo.seller3@example.com', '400001'],
    ];

    /**
     * [category slug, name, price, MRP, stock, seller index, colour, COD allowed, highlights].
     * Includes edge cases for checkout: one out of stock, one without COD, one above the
     * ₹5,000 COD limit, and some above the ₹2,000 COD OTP threshold.
     */
    private const PRODUCTS = [
        ['silk-sarees', 'Kanjivaram Art Silk Saree with Zari Border', '1299', '3499', 25, 0, '#9F1239', true, ['Art silk with zari border', 'Saree length 5.5 m + 0.8 m blouse piece', 'Dry clean only']],
        ['cotton-sarees', 'Handloom Cotton Saree, Temple Border', '749', '1599', 40, 0, '#B45309', true, ['Breathable handloom cotton', 'Contrast temple border', 'Hand wash separately']],
        ['georgette-sarees', 'Printed Georgette Saree with Blouse Piece', '599', '1899', 30, 0, '#7C3AED', true, ['Lightweight georgette', 'Floral digital print', 'Unstitched blouse piece']],
        ['banarasi-sarees', 'Banarasi Woven Silk Saree', '2499', '6999', 12, 0, '#B91C1C', true, ['Woven Banarasi motifs', 'Rich pallu', 'Comes in a gift box']],
        ['anarkali-kurtis', 'Flared Anarkali Kurti with Gota Work', '699', '1799', 35, 0, '#BE185D', true, ['Rayon blend, flared fit', 'Gota patti detailing', 'Calf length']],
        ['straight-kurtis', 'Straight Cotton Kurti, Block Print', '449', '999', 60, 0, '#0E7490', true, ['Pure cotton', 'Hand block print', 'Three-quarter sleeves']],
        ['kurta-palazzo-sets', 'Kurta and Palazzo Set, Floral Print', '899', '2299', 28, 0, '#15803D', true, ['Two-piece set', 'Elasticated palazzo', 'Machine wash']],
        ['bridal-lehengas', 'Embroidered Bridal Lehenga Choli Set', '6499', '14999', 5, 0, '#9F1239', true, ['Heavy embroidery with sequins', 'Semi-stitched choli', 'Net dupatta included']],
        ['silk-dupattas', 'Silk Dupatta with Tassels', '349', '899', 0, 0, '#A16207', true, ['Art silk', 'Tassel edges', '2.25 m length']],
        ['crop-tops', 'Ribbed Crop Top, Square Neck', '299', '799', 80, 1, '#DB2777', true, ['Stretchable rib knit', 'Square neckline', 'Regular fit']],
        ['maxi-dresses', 'Tiered Floral Maxi Dress', '799', '1999', 22, 1, '#C2410C', true, ['Viscose rayon', 'Tiered skirt', 'Adjustable tie-up waist']],
        ['skinny-jeans', 'High-Rise Skinny Jeans, Stone Wash', '649', '1699', 45, 1, '#1E40AF', true, ['Stretch denim', 'High rise', 'Five-pocket styling']],
        ['night-suits', 'Cotton Night Suit Set, Checks', '549', '1299', 33, 1, '#4338CA', true, ['Soft cotton', 'Button-down top', 'Elasticated pyjama']],
        ['polo-t-shirts', 'Men Pique Polo T-Shirt', '399', '999', 70, 1, '#0F766E', true, ['Cotton pique', 'Ribbed collar', 'Regular fit']],
        ['round-neck-t-shirts', 'Men Graphic Round Neck T-Shirt', '249', '699', 90, 1, '#334155', true, ['100% cotton', 'Screen-printed graphic', 'Crew neck']],
        ['casual-shirts', 'Men Linen Blend Casual Shirt', '699', '1799', 26, 1, '#047857', true, ['Linen-cotton blend', 'Full sleeves', 'Curved hem']],
        ['slim-fit-jeans', 'Men Slim Fit Jeans, Dark Blue', '799', '2199', 40, 1, '#1E3A8A', true, ['Stretch denim', 'Slim fit', 'Mid rise']],
        ['sports-shoes', 'Men Lightweight Running Shoes', '999', '2499', 18, 1, '#0369A1', true, ['Mesh upper', 'Cushioned sole', 'Lace-up']],
        ['double-bedsheets', 'Cotton Double Bedsheet with 2 Pillow Covers', '599', '1499', 50, 2, '#0D9488', true, ['144 TC cotton', 'Fits a queen bed', 'Two pillow covers']],
        ['window-curtains', 'Blackout Window Curtains, Set of 2', '899', '2199', 20, 2, '#475569', true, ['Blackout polyester', '5 ft each', 'Eyelet rings']],
        ['cookware-sets', 'Non-Stick Cookware Set, 3 Pieces', '1499', '3299', 15, 2, '#B91C1C', true, ['Kadai, tawa and fry pan', 'Induction friendly', 'PFOA free coating']],
        ['storage-containers', 'Airtight Kitchen Containers, Set of 6', '449', '1099', 55, 2, '#65A30D', true, ['BPA-free plastic', 'Stackable', 'Leak-proof lids']],
        ['wall-decor', 'Wooden Wall Shelf Decor, Set of 3', '699', '1599', 14, 2, '#92400E', true, ['Engineered wood', 'Wall mounted', 'Screws included']],
        ['earbuds', 'Wireless Earbuds with 30h Playback', '1199', '3999', 35, 2, '#111827', true, ['Bluetooth 5.3', '30 hours with case', 'Touch controls']],
        ['bluetooth-speakers', 'Portable Bluetooth Speaker, 10 W', '1499', '3499', 16, 2, '#1D4ED8', true, ['10 W output', 'Splash resistant', '12 hours playback']],
        ['smart-watches', 'Smart Watch with Calling and SpO2', '2199', '5999', 10, 2, '#0F172A', false, ['1.8 inch display', 'Bluetooth calling', 'Heart rate and SpO2']],
        ['power-banks', '10000 mAh Fast-Charging Power Bank', '799', '1999', 30, 2, '#374151', true, ['10000 mAh', '22.5 W fast charge', 'Dual output']],
        ['jhumkas', 'Oxidised Silver Jhumka Earrings', '199', '599', 100, 0, '#6B7280', true, ['Oxidised finish', 'Lightweight', 'Hook closure']],
        ['kundan-sets', 'Kundan Necklace Set with Earrings', '899', '2499', 12, 0, '#CA8A04', true, ['Kundan and pearl', 'Adjustable dori', 'Matching earrings']],
        ['sling-bags', 'Quilted Sling Bag with Chain Strap', '549', '1499', 38, 1, '#9D174D', true, ['Faux leather', 'Chain strap', 'Magnetic flap']],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('The demo catalog is not for production.');

            return;
        }

        if (Seller::query()->where('code', self::SELLERS[0][0])->exists()) {
            $this->command?->warn('Demo catalog already seeded (supplier '.self::SELLERS[0][0].' exists). Nothing to do.');

            return;
        }

        $categories = CatalogCategory::query()->where('is_leaf', config('core.yes'))->pluck('id', 'slug');

        if ($categories->isEmpty()) {
            $this->command?->error('No catalog categories. Run: php artisan module:seed Catalog');

            return;
        }

        $this->seedPincodes();
        $sellers = $this->seedSellers();
        $this->seedBuyer();

        $created = 0;

        foreach (self::PRODUCTS as $index => [$slug, $name, $price, $mrp, $stock, $sellerIndex, $colour, $cod, $highlights]) {
            $categoryId = $categories[$slug] ?? $categories->first();
            $this->createProduct($sellers[$sellerIndex], (int) $categoryId, $name, $price, $mrp, $stock, $colour, $cod, $highlights, $index);
            $created++;
        }

        Artisan::call('storefront:reindex');

        $this->command?->info("Demo catalog: {$created} products from ".count($sellers).' suppliers.');
        $this->command?->line('Buyer: '.self::BUYER_EMAIL.' / '.self::PASSWORD.' (password login) or OTP to '.self::BUYER_PHONE.' (code in storage/logs/sms.log).');
        $this->command?->line('Suppliers: '.implode(', ', array_column(self::SELLERS, 2)).' / '.self::PASSWORD);
    }

    protected function seedPincodes(): void
    {
        foreach (self::PINCODES as [$pincode, $office, $district]) {
            if (DirectoryPincode::query()->where('pincode', $pincode)->exists()) {
                continue;
            }

            DirectoryPincode::factory()->create([
                'pincode' => $pincode,
                'office_name' => $office,
                'district' => $district,
                'is_serviceable' => config('core.yes'),
                'cod_allowed' => config('core.yes'),
                'zone' => 'metro',
            ]);
        }
    }

    /**
     * @return list<Seller>
     */
    protected function seedSellers(): array
    {
        $sellers = [];

        foreach (self::SELLERS as $position => [$code, $storeName, $email, $pincode]) {
            $seller = Seller::factory()->active()->create([
                'code' => $code,
                'legal_name' => $storeName.' Pvt Ltd',
                'store_name' => $storeName,
                'store_slug' => Str::slug($storeName),
                'email' => $email,
                'phone' => '+91980000000'.($position + 1),
            ]);

            SellerUser::factory()->create([
                'seller_id' => $seller->id,
                'name' => $storeName.' Owner',
                'email' => $email,
                'phone' => '+91981000000'.($position + 1),
                'password' => self::PASSWORD,
            ]);

            SellerWarehouse::factory()->create(['seller_id' => $seller->id, 'pincode' => $pincode]);

            $sellers[] = $seller;
        }

        return $sellers;
    }

    protected function seedBuyer(): void
    {
        if (Customer::query()->where('email', self::BUYER_EMAIL)->orWhere('phone', self::BUYER_PHONE)->exists()) {
            return;
        }

        Customer::factory()->withPhone(self::BUYER_PHONE)->create([
            'first_name' => 'Demo',
            'last_name' => 'Buyer',
            'email' => self::BUYER_EMAIL,
            'password' => Hash::make(self::PASSWORD),
            'contact_number' => '9876500001',
        ]);
    }

    /**
     * @param  list<string>  $highlights
     */
    protected function createProduct(Seller $seller, int $categoryId, string $name, string $price, string $mrp, int $stock, string $colour, bool $cod, array $highlights, int $index): void
    {
        $product = Product::factory()->live()->forSeller($seller)->create([
            'catalog_category_id' => $categoryId,
            'slug' => Str::slug($name),
            'name' => $name,
            'description' => '<p>'.e($name).' from '.e((string) $seller->store_name).'. This is demo data for trying the storefront; the photos are generated placeholders.</p>',
            'highlights' => $highlights,
            'is_cod_allowed' => $cod ? config('core.yes') : config('core.no'),
            'published_at' => now()->subMinutes(count(self::PRODUCTS) - $index),
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'seller_id' => $seller->id,
            'seller_sku' => 'DEMO-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
            'combination_key' => ProductVariant::DEFAULT_COMBINATION,
            'price' => $price,
            'mrp' => $mrp,
            'stock' => $stock,
            'position' => 0,
        ]);

        foreach (['Front view', 'Detail', 'Styled'] as $position => $label) {
            $this->addImage($product, $name, $label, $colour, $position);
        }

        app(ProductAggregateService::class)->refresh($product->id);
    }

    /**
     * Draw a placeholder photo, then store it through ProductImageService (re-encoding,
     * resized copies and the duplicate hash), exactly like a seller upload.
     */
    protected function addImage(Product $product, string $name, string $label, string $colour, int $position): void
    {
        $file = $this->drawImage($name, $label, $colour, $position);

        try {
            $image = app(ProductImageService::class)->store(new UploadedFile($file, 'demo.jpg', 'image/jpeg', null, true));
        } finally {
            File::delete($file);
        }

        ProductImage::query()->create([
            'product_id' => $product->id,
            'path' => $image->path,
            'position' => $position,
            'width' => $image->width,
            'height' => $image->height,
            'content_hash' => $image->contentHash,
            'alt' => $name.' - '.$label,
        ]);
    }

    /**
     * An 800×1067 (3:4) JPEG: a gradient in the product colour, a soft shape that changes
     * per view, the product name and a "Demo image" note.
     */
    protected function drawImage(string $title, string $label, string $colour, int $view): string
    {
        [$width, $height] = [800, 1067];
        [$red, $green, $blue] = sscanf($colour, '#%02x%02x%02x');
        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, true);

        for ($y = 0; $y < $height; $y++) {
            $mix = 0.25 + 0.75 * ($y / $height);
            $line = imagecolorallocate(
                $canvas,
                (int) (245 + ($red - 245) * $mix),
                (int) (245 + ($green - 245) * $mix),
                (int) (245 + ($blue - 245) * $mix),
            );
            imageline($canvas, 0, $y, $width, $y, $line);
        }

        $soft = imagecolorallocatealpha($canvas, 255, 255, 255, 85);
        $solid = imagecolorallocatealpha($canvas, $red, $green, $blue, 40);

        match ($view) {
            0 => imagefilledellipse($canvas, 400, 430, 560, 560, $soft),
            1 => imagefilledrectangle($canvas, 140, 200, 660, 700, $soft),
            default => imagefilledpolygon($canvas, [400, 150, 700, 650, 100, 650], $soft),
        };
        imagefilledellipse($canvas, 400, 430, 260, 260, $solid);

        $white = imagecolorallocate($canvas, 255, 255, 255);
        $shadow = imagecolorallocatealpha($canvas, 0, 0, 0, 80);
        $font = 'C:/Windows/Fonts/arialbd.ttf';
        $font = is_file($font) ? $font : (is_file('/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf') ? '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf' : null);

        if ($font !== null && function_exists('imagettftext')) {
            $y = 800;

            foreach ($this->wrap($title, $font, 40, 680) as $line) {
                $box = imagettfbbox(40, 0, $font, $line);
                $x = (int) (($width - ($box[2] - $box[0])) / 2);
                imagettftext($canvas, 40, 0, $x + 2, $y + 2, $shadow, $font, $line);
                imagettftext($canvas, 40, 0, $x, $y, $white, $font, $line);
                $y += 56;
            }

            $note = $label.' · Demo image';
            $box = imagettfbbox(22, 0, $font, $note);
            imagettftext($canvas, 22, 0, (int) (($width - ($box[2] - $box[0])) / 2), $height - 50, $white, $font, $note);
        } else {
            imagestring($canvas, 5, 40, 800, Str::limit($title, 90), $white);
            imagestring($canvas, 4, 40, $height - 60, $label.' - Demo image', $white);
        }

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'demo-product-'.Str::random(16).'.jpg';
        imagejpeg($canvas, $path, 88);

        return $path;
    }

    /**
     * @return list<string>
     */
    private function wrap(string $text, string $font, int $size, int $maxWidth): array
    {
        $lines = [];
        $current = '';

        foreach (explode(' ', $text) as $word) {
            $candidate = trim($current.' '.$word);
            $box = imagettfbbox($size, 0, $font, $candidate);

            if ($current !== '' && ($box[2] - $box[0]) > $maxWidth) {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }

        $lines[] = $current;

        return array_slice($lines, 0, 4);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Catalog\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Attribute\Models\Attribute;
use Modules\Catalog\Events\CatalogTreeChanged;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Catalog\Models\CatalogCategoryAttribute;
use Modules\Catalog\Models\CatalogSizeChart;
use Modules\Catalog\Services\CategoryTreeService;
use Modules\Catalog\Support\CatalogSettings;

/**
 * The starter 3-level category tree with commerce defaults, attribute mappings and two
 * size charts. Run after CatalogAttributeSeeder.
 *
 * GST rates and HSN codes are starting values for the finance team to confirm against the
 * current GST rate notifications before launch; admins can change them per category.
 *
 * Idempotent: categories are matched by slug and mapping rows by (category, attribute),
 * so nothing an admin changed is overwritten on a re-run.
 */
class CatalogTaxonomySeeder extends Seeder
{
    /**
     * @var array<string, int>
     */
    protected array $attributeIds = [];

    /**
     * @var array<string, array<string, int>>
     */
    protected array $optionIds = [];

    /**
     * @var array<string, int>
     */
    protected array $sizeCharts = [];

    public function __construct(protected CategoryTreeService $tree) {}

    public function run(): void
    {
        $this->loadAttributes();

        DB::transaction(function (): void {
            $this->seedSizeCharts();

            foreach ($this->taxonomy() as $index => $node) {
                $this->seedNode($node, null, $index);
            }
        });

        event(new CatalogTreeChanged(null, 'seeded'));
    }

    /**
     * @param  array{0: string, 1: string|null, 2: array<string, mixed>, 3: list<array<string, mixed>>, 4: list<mixed>}|string  $node
     */
    protected function seedNode(array|string $node, ?CatalogCategory $parent, int $sortOrder): void
    {
        [$name, $hindi, $defaults, $mappings, $children] = is_string($node) ? [$node, null, [], [], []] : $node;

        $category = $this->findOrCreate($name, $hindi, $parent, $defaults, $sortOrder);

        foreach ($mappings as $index => $mapping) {
            $this->map($category, $mapping, $index);
        }

        foreach ($children as $index => $child) {
            $this->seedNode($child, $category, $index);
        }
    }

    /**
     * @param  array<string, mixed>  $defaults
     */
    protected function findOrCreate(string $name, ?string $hindi, ?CatalogCategory $parent, array $defaults, int $sortOrder): CatalogCategory
    {
        $slug = Str::slug($name);
        $existing = CatalogCategory::withTrashed()->where('slug', $slug)->first();

        if ($existing !== null && $existing->parent_id !== $parent?->id) {
            $slug = Str::slug(($parent?->slug ?? '').'-'.$name);
            $existing = CatalogCategory::withTrashed()->where('slug', $slug)->first();
        }

        if ($existing !== null) {
            return $existing;
        }

        if (isset($defaults['size_chart'])) {
            $defaults['size_chart_id'] = $this->sizeCharts[$defaults['size_chart']] ?? null;
            unset($defaults['size_chart']);
        }

        $translations = [];
        foreach (CatalogSettings::contentLocales() as $locale) {
            $translations[$locale] = [
                'name' => $locale === 'hi' && $hindi ? $hindi : $name,
                'meta_title' => $name,
            ];
        }

        return $this->tree->create($defaults + $translations + [
            'parent_id' => $parent?->id,
            'slug' => $slug,
            'sort_order' => $sortOrder,
            'show_in_menu' => config('core.yes'),
            'status' => config('core.enabled'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $mapping  attribute code + flags + allowed option codes
     */
    protected function map(CatalogCategory $category, array $mapping, int $sortOrder): void
    {
        $attributeId = $this->attributeIds[$mapping['attribute']] ?? null;

        if ($attributeId === null) {
            return;
        }

        $allowed = null;
        if (! empty($mapping['allowed'])) {
            $allowed = array_values(array_filter(array_map(fn (string $code): ?int => $this->optionIds[$mapping['attribute']][$code] ?? null, $mapping['allowed'])));
        }

        $yes = config('core.yes');
        $no = config('core.no');

        CatalogCategoryAttribute::query()->firstOrCreate(
            ['catalog_category_id' => $category->id, 'attribute_id' => $attributeId],
            [
                'is_variant_axis' => ! empty($mapping['axis']) ? $yes : $no,
                'is_filterable' => ! empty($mapping['filter']) ? $yes : $no,
                'is_required' => ! empty($mapping['required']) ? $yes : $no,
                'show_on_card' => ! empty($mapping['card']) ? $yes : $no,
                'sort_order' => $mapping['sort'] ?? $sortOrder + 1,
                'allowed_option_ids' => $allowed ?: null,
            ],
        );
    }

    protected function loadAttributes(): void
    {
        $attributes = Attribute::query()
            ->with('attributeOption')
            ->whereIn('code', ['size', 'colour', 'fabric', 'pattern', 'sleeve-length', 'neck', 'occasion', 'material', 'ideal-for'])
            ->get();

        foreach ($attributes as $attribute) {
            $this->attributeIds[$attribute->code] = (int) $attribute->id;
            $this->optionIds[$attribute->code] = $attribute->attributeOption->pluck('id', 'custom_option')->map(fn ($id): int => (int) $id)->all();
        }
    }

    protected function seedSizeCharts(): void
    {
        $charts = [
            'women_top' => ['Women Tops & Kurtis (in)', ['Bust', 'Waist', 'Length'], [
                ['XS', '32', '26', '44'], ['S', '34', '28', '44'], ['M', '36', '30', '45'], ['L', '38', '32', '45'],
                ['XL', '40', '34', '46'], ['XXL', '42', '36', '46'], ['3XL', '44', '38', '47'],
            ]],
            'men_top' => ['Men T-Shirts & Shirts (in)', ['Chest', 'Shoulder', 'Length'], [
                ['S', '38', '17', '27'], ['M', '40', '17.5', '28'], ['L', '42', '18', '29'], ['XL', '44', '18.5', '30'],
                ['XXL', '46', '19', '31'], ['3XL', '48', '19.5', '31.5'],
            ]],
        ];

        foreach ($charts as $key => [$name, $columns, $rows]) {
            $chart = CatalogSizeChart::query()->firstOrCreate(['name' => $name], [
                'unit' => 'in',
                'columns' => $columns,
                'rows' => array_map(fn (array $row): array => ['size' => $row[0], 'values' => array_slice($row, 1)], $rows),
                'status' => config('core.enabled'),
            ]);

            $this->sizeCharts[$key] = (int) $chart->id;
        }
    }

    /**
     * [name, Hindi name, defaults, mappings, children]; leaves are plain names.
     *
     * @return list<array{0: string, 1: string|null, 2: array<string, mixed>, 3: list<array<string, mixed>>, 4: list<mixed>}>
     */
    protected function taxonomy(): array
    {
        $apparel = fn (int $gst = 5, string $hsn = '6204'): array => ['gst_percent' => $gst, 'hsn_code' => $hsn, 'return_window_days' => 7, 'return_type' => 'return_exchange', 'dispatch_sla_hours' => 48];
        $colourAxis = ['attribute' => 'colour', 'axis' => true, 'filter' => true, 'required' => true, 'sort' => 2];
        $apparelAttributes = [
            $colourAxis,
            ['attribute' => 'fabric', 'filter' => true, 'sort' => 3],
            ['attribute' => 'pattern', 'filter' => true, 'sort' => 4],
            ['attribute' => 'occasion', 'filter' => true, 'sort' => 6],
        ];
        $size = fn (array $allowed, bool $card = false): array => ['attribute' => 'size', 'axis' => true, 'filter' => true, 'required' => true, 'card' => $card, 'sort' => 1, 'allowed' => $allowed];
        $letter = ['xs', 's', 'm', 'l', 'xl', 'xxl', '3xl'];
        $letterPlus = [...$letter, '4xl', '5xl'];
        $free = ['free-size'];
        $waist = ['w28', 'w30', 'w32', 'w34', 'w36', 'w38', 'w40', 'w42', 'w44'];
        $kids = ['age-0-3m', 'age-3-6m', 'age-6-12m', 'age-1-2y', 'age-2-3y', 'age-3-4y', 'age-4-5y', 'age-5-6y', 'age-6-7y', 'age-7-8y', 'age-8-9y', 'age-9-10y', 'age-10-11y', 'age-11-12y', 'age-12-13y', 'age-13-14y'];
        $shoe = ['uk3', 'uk4', 'uk5', 'uk6', 'uk7', 'uk8', 'uk9', 'uk10', 'uk11'];
        $material = ['attribute' => 'material', 'filter' => true, 'sort' => 3];
        $sleeveAndNeck = [['attribute' => 'sleeve-length', 'filter' => true, 'sort' => 5], ['attribute' => 'neck', 'filter' => true, 'sort' => 7]];
        $footwear = ['gst_percent' => 12, 'hsn_code' => '6402', 'return_window_days' => 7];

        return [
            ['Ethnic Wear', 'एथनिक वियर', $apparel(), $apparelAttributes, [
                ['Sarees', 'साड़ियाँ', ['hsn_code' => '5407'], [$size($free, true)], ['Silk Sarees', 'Cotton Sarees', 'Georgette Sarees', 'Chiffon Sarees', 'Banarasi Sarees']],
                ['Kurtis', 'कुर्तियाँ', ['hsn_code' => '6206', 'size_chart' => 'women_top'], [$size($letterPlus), ...$sleeveAndNeck], ['Anarkali Kurtis', 'Straight Kurtis', 'A-Line Kurtis', 'Kaftan Kurtis']],
                ['Kurta Sets', 'कुर्ता सेट', ['hsn_code' => '6204', 'size_chart' => 'women_top'], [$size($letterPlus), ...$sleeveAndNeck], ['Kurta Palazzo Sets', 'Kurta Pant Sets', 'Sharara Sets']],
                ['Lehengas', 'लहंगे', ['hsn_code' => '6204'], [$size($letter)], ['Lehenga Cholis', 'Party Lehengas', 'Bridal Lehengas']],
                ['Dupattas', 'दुपट्टे', ['hsn_code' => '6214'], [$size($free, true)], ['Cotton Dupattas', 'Silk Dupattas', 'Embroidered Dupattas']],
                ['Ethnic Bottomwear', 'एथनिक बॉटमवियर', ['hsn_code' => '6204'], [$size($letterPlus)], ['Leggings', 'Palazzos', 'Salwars', 'Patialas']],
            ]],
            ['Western Wear', 'वेस्टर्न वियर', $apparel(), $apparelAttributes, [
                ['Tops & Tunics', 'टॉप्स और ट्यूनिक', ['hsn_code' => '6206', 'size_chart' => 'women_top'], [$size($letter), ...$sleeveAndNeck], ['Crop Tops', 'Shirts & Blouses', 'Tunics', 'Women T-Shirts']],
                ['Dresses', 'ड्रेसेज़', ['hsn_code' => '6204'], [$size($letter), ...$sleeveAndNeck], ['Maxi Dresses', 'Midi Dresses', 'Bodycon Dresses', 'A-Line Dresses']],
                ['Jeans & Jeggings', 'जींस और जेगिंग्स', ['hsn_code' => '6204'], [$size($waist)], ['Skinny Jeans', 'Straight Jeans', 'Jeggings']],
                ['Co-ords', 'को-ऑर्ड सेट', ['hsn_code' => '6204'], [$size($letter)], ['Shirt Co-ords', 'Top & Skirt Sets', 'Top & Trouser Sets']],
                ['Nightwear', 'नाइटवियर', ['hsn_code' => '6208'], [$size($letterPlus)], ['Night Suits', 'Nighties', 'Loungewear']],
                ['Lingerie', 'लॉन्जरी', ['hsn_code' => '6212'], [$size($letter)], ['Bras', 'Panties', 'Shapewear']],
            ]],
            ['Men', 'पुरुष', $apparel(5, '6203'), $apparelAttributes, [
                ['T-Shirts', 'टी-शर्ट', ['hsn_code' => '6109', 'size_chart' => 'men_top'], [$size($letterPlus), ...$sleeveAndNeck], ['Polo T-Shirts', 'Round Neck T-Shirts', 'V-Neck T-Shirts']],
                ['Shirts', 'शर्ट', ['hsn_code' => '6205', 'size_chart' => 'men_top'], [$size($letterPlus)], ['Casual Shirts', 'Formal Shirts', 'Denim Shirts']],
                ['Jeans', 'जींस', ['hsn_code' => '6203'], [$size($waist)], ['Slim Fit Jeans', 'Regular Fit Jeans']],
                ['Trousers', 'ट्राउज़र', ['hsn_code' => '6203'], [$size($waist)], ['Chinos', 'Formal Trousers', 'Track Pants']],
                ['Ethnic Wear', 'एथनिक वियर', ['hsn_code' => '6203'], [$size($letterPlus)], ['Kurtas', 'Men Kurta Sets', 'Nehru Jackets']],
                ['Innerwear', 'इनरवियर', ['hsn_code' => '6107'], [$size($letter)], ['Briefs', 'Trunks', 'Vests']],
                ['Footwear', 'फुटवियर', $footwear, [$size($shoe), $material], ['Sports Shoes', 'Casual Shoes', 'Formal Shoes', 'Sandals & Floaters']],
            ]],
            ['Kids & Baby', 'बच्चे और शिशु', $apparel(5, '6209'), [$colourAxis, ['attribute' => 'ideal-for', 'filter' => true, 'sort' => 5]], [
                ['Boys', 'लड़के', [], [$size($kids), ['attribute' => 'fabric', 'filter' => true, 'sort' => 3]], ['Boys T-Shirts', 'Boys Shirts', 'Boys Shorts', 'Boys Ethnic Sets']],
                ['Girls', 'लड़कियाँ', [], [$size($kids), ['attribute' => 'fabric', 'filter' => true, 'sort' => 3]], ['Frocks', 'Girls Tops', 'Girls Ethnic Wear', 'Girls Leggings']],
                ['Infants', 'शिशु', [], [$size(array_slice($kids, 0, 6)), ['attribute' => 'fabric', 'filter' => true, 'sort' => 3]], ['Rompers', 'Baby Sets', 'Sleepsuits']],
                ['Toys', 'खिलौने', ['gst_percent' => 12, 'hsn_code' => '9503'], [$material], ['Soft Toys', 'Educational Toys', 'Remote Control Toys']],
            ]],
            ['Home & Living', 'घर और रहन-सहन', ['gst_percent' => 18, 'hsn_code' => '3924', 'return_window_days' => 7, 'return_type' => 'return_exchange', 'dispatch_sla_hours' => 48], [$colourAxis, $material], [
                ['Bedsheets', 'बेडशीट', ['gst_percent' => 5, 'hsn_code' => '6302'], [], ['Double Bedsheets', 'Single Bedsheets', 'Bedding Sets']],
                ['Curtains', 'पर्दे', ['gst_percent' => 5, 'hsn_code' => '6303'], [], ['Door Curtains', 'Window Curtains']],
                ['Kitchen Tools', 'रसोई के उपकरण', ['gst_percent' => 12, 'hsn_code' => '7323'], [], ['Cookware Sets', 'Kitchen Gadgets', 'Storage Containers']],
                ['Storage', 'स्टोरेज', [], [], ['Organisers', 'Storage Boxes']],
                ['Home Decor', 'घर की सजावट', ['hsn_code' => '4421'], [], ['Wall Decor', 'Showpieces', 'Artificial Flowers']],
            ]],
            ['Beauty & Care', 'सौंदर्य और देखभाल', ['gst_percent' => 18, 'hsn_code' => '3304', 'return_window_days' => 0, 'return_type' => 'none', 'dispatch_sla_hours' => 48], [], [
                ['Makeup', 'मेकअप', [], [['attribute' => 'colour', 'axis' => true, 'filter' => true, 'sort' => 1]], ['Lipsticks', 'Foundations', 'Kajal & Eyeliners', 'Nail Polish']],
                ['Skincare', 'स्किनकेयर', [], [], ['Face Wash', 'Moisturisers', 'Sunscreens']],
                ['Haircare', 'बालों की देखभाल', ['hsn_code' => '3305'], [], ['Hair Oil', 'Shampoos', 'Hair Accessories']],
                ['Personal Care', 'व्यक्तिगत देखभाल', ['hsn_code' => '3307'], [], ['Bath & Body', 'Deodorants', 'Grooming Tools']],
                ['Wellness', 'वेलनेस', ['hsn_code' => '9019', 'return_window_days' => 7, 'return_type' => 'return_exchange'], [], ['Massagers', 'Yoga Mats']],
            ]],
            ['Jewellery & Accessories', 'आभूषण और एक्सेसरीज़', ['gst_percent' => 3, 'hsn_code' => '7117', 'return_window_days' => 7, 'return_type' => 'return_exchange', 'dispatch_sla_hours' => 48], [$colourAxis, $material], [
                ['Jewellery Sets', 'ज्वेलरी सेट', [], [], ['Kundan Sets', 'Oxidised Sets', 'Necklace Sets']],
                ['Earrings', 'झुमके और बालियाँ', [], [], ['Jhumkas', 'Studs', 'Hoops']],
                ['Bangles', 'चूड़ियाँ', [], [], ['Glass Bangles', 'Metal Bangles', 'Kadas']],
                ['Watches', 'घड़ियाँ', ['gst_percent' => 18, 'hsn_code' => '9102'], [], ['Men Watches', 'Women Watches', 'Kids Watches']],
                ['Sunglasses', 'धूप के चश्मे', ['gst_percent' => 18, 'hsn_code' => '9004'], [], ['Men Sunglasses', 'Women Sunglasses']],
                ['Belts', 'बेल्ट', ['gst_percent' => 18, 'hsn_code' => '4203'], [], ['Men Belts', 'Women Belts']],
            ]],
            ['Bags & Footwear', 'बैग और फुटवियर', ['gst_percent' => 18, 'hsn_code' => '4202', 'return_window_days' => 7, 'return_type' => 'return_exchange', 'dispatch_sla_hours' => 48], [$colourAxis, $material], [
                ['Handbags', 'हैंडबैग', [], [], ['Sling Bags', 'Tote Bags', 'Clutches']],
                ['Backpacks', 'बैकपैक', [], [], ['College Backpacks', 'Laptop Backpacks']],
                ['Wallets', 'वॉलेट', [], [], ['Men Wallets', 'Women Wallets']],
                ['Women Footwear', 'महिला फुटवियर', $footwear, [$size($shoe)], ['Flats', 'Heels', 'Women Sandals', 'Women Sports Shoes']],
                ['Men Footwear', 'पुरुष फुटवियर', $footwear, [$size($shoe)], ['Loafers', 'Slippers & Flip Flops', 'Boots']],
            ]],
            ['Gadgets & Electronics', 'गैजेट्स और इलेक्ट्रॉनिक्स', ['gst_percent' => 18, 'hsn_code' => '8517', 'return_window_days' => 7, 'return_type' => 'exchange_only', 'dispatch_sla_hours' => 48], [$colourAxis], [
                ['Mobile Accessories', 'मोबाइल एक्सेसरीज़', ['hsn_code' => '3926'], [], ['Phone Cases', 'Chargers', 'Cables', 'Power Banks', 'Screen Guards']],
                ['Audio', 'ऑडियो', ['hsn_code' => '8518'], [], ['Earbuds', 'Headphones', 'Bluetooth Speakers']],
                ['Smart Wearables', 'स्मार्ट वियरेबल्स', ['hsn_code' => '8517'], [], ['Smart Watches', 'Fitness Bands']],
                ['Appliances', 'उपकरण', ['hsn_code' => '8516'], [], ['Hair Dryers & Straighteners', 'Trimmers', 'Kitchen Appliances']],
            ]],
        ];
    }
}

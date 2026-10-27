<?php

declare(strict_types=1);

namespace Modules\Catalog\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Attribute\Models\Attribute;
use Modules\Attribute\Models\AttributeOption;
use Modules\Catalog\Support\CatalogSettings;

/**
 * Product attributes in the existing Attribute tables: Size, Colour (hex code in
 * custom_option), Fabric, Pattern, Sleeve Length, Neck, Occasion, Material, Ideal For.
 *
 * Idempotent: an attribute whose code exists is left alone (admins may have edited it),
 * only its missing options are added.
 */
class CatalogAttributeSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach ($this->attributes() as $code => [$name, $hindiName, $inputType, $options]) {
                $attribute = Attribute::query()->where('code', $code)->first();

                if ($attribute === null) {
                    $attribute = Attribute::query()->create([
                        'code' => $code,
                        'input_type' => $inputType,
                        'custom_value' => config('core.yes'),
                        'is_required' => config('core.no'),
                    ]);

                    foreach (CatalogSettings::contentLocales() as $locale) {
                        $attribute->translations()->forceCreate(['name' => $locale === 'hi' ? $hindiName : $name, 'locale' => $locale]);
                    }
                }

                $this->seedOptions($attribute, $options);
            }
        });
    }

    /**
     * @param  array<string, string>  $options  custom_option code => name
     */
    protected function seedOptions(Attribute $attribute, array $options): void
    {
        $existing = AttributeOption::query()->where('attribute_id', $attribute->id)->pluck('custom_option')->all();
        $sortOrder = (int) AttributeOption::query()->where('attribute_id', $attribute->id)->max('sort_order');

        foreach ($options as $code => $name) {
            if (in_array($code, $existing, true)) {
                continue;
            }

            $option = AttributeOption::query()->create([
                'attribute_id' => $attribute->id,
                'custom_option' => $code,
                'default' => config('core.no'),
                'sort_order' => ++$sortOrder,
            ]);

            foreach (CatalogSettings::contentLocales() as $locale) {
                $option->translations()->forceCreate(['name' => $name, 'locale' => $locale]);
            }
        }
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: array<string, string>}>
     */
    protected function attributes(): array
    {
        return [
            'size' => ['Size', 'साइज़', 'select', $this->sizes()],
            'colour' => ['Colour', 'रंग', 'select', $this->colours()],
            'fabric' => ['Fabric', 'कपड़ा', 'select', $this->named([
                'Cotton', 'Cotton Blend', 'Silk', 'Art Silk', 'Georgette', 'Chiffon', 'Crepe', 'Rayon', 'Viscose', 'Linen',
                'Polyester', 'Denim', 'Wool', 'Net', 'Velvet', 'Satin', 'Chanderi', 'Khadi', 'Lycra', 'Modal',
            ])],
            'pattern' => ['Pattern', 'पैटर्न', 'select', $this->named([
                'Solid', 'Printed', 'Embroidered', 'Striped', 'Checked', 'Floral', 'Woven Design', 'Self Design',
                'Embellished', 'Geometric', 'Ethnic Motifs', 'Tie and Dye', 'Colourblocked',
            ])],
            'sleeve-length' => ['Sleeve Length', 'आस्तीन की लंबाई', 'select', $this->named([
                'Sleeveless', 'Short Sleeves', 'Three-Quarter Sleeves', 'Long Sleeves', 'Cap Sleeves',
            ])],
            'neck' => ['Neck', 'गला', 'select', $this->named([
                'Round Neck', 'V-Neck', 'Collar', 'Mandarin Collar', 'Boat Neck', 'Sweetheart Neck', 'Square Neck',
                'Keyhole Neck', 'Halter Neck', 'Polo Collar', 'High Neck',
            ])],
            'occasion' => ['Occasion', 'अवसर', 'select', $this->named([
                'Casual', 'Daily Wear', 'Party', 'Festive', 'Wedding', 'Formal', 'Sports', 'Lounge',
            ])],
            'material' => ['Material', 'सामग्री', 'select', $this->named([
                'Plastic', 'Metal', 'Stainless Steel', 'Aluminium', 'Wood', 'Glass', 'Ceramic', 'Leather',
                'Synthetic Leather', 'Fabric', 'Rubber', 'Silicone', 'Brass', 'Alloy', 'Canvas', 'Jute',
            ])],
            'ideal-for' => ['Ideal For', 'किसके लिए', 'select', $this->named([
                'Women', 'Men', 'Girls', 'Boys', 'Unisex', 'Kids', 'Infants',
            ])],
        ];
    }

    /**
     * Letter sizes, free size, waist sizes, kids age bands and shoe sizes in one attribute;
     * each category narrows them with allowed_option_ids.
     *
     * @return array<string, string>
     */
    protected function sizes(): array
    {
        $sizes = [];
        foreach (['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL', '4XL', '5XL'] as $size) {
            $sizes[strtolower($size)] = $size;
        }
        $sizes['free-size'] = 'Free Size';

        for ($waist = 28; $waist <= 44; $waist += 2) {
            $sizes['w'.$waist] = (string) $waist;
        }

        foreach (['0-3M', '3-6M', '6-12M', '1-2Y', '2-3Y', '3-4Y', '4-5Y', '5-6Y', '6-7Y', '7-8Y', '8-9Y', '9-10Y', '10-11Y', '11-12Y', '12-13Y', '13-14Y'] as $band) {
            $sizes['age-'.strtolower($band)] = str_replace(['M', 'Y'], [' Months', ' Years'], $band);
        }

        for ($uk = 3; $uk <= 11; $uk++) {
            $sizes['uk'.$uk] = 'UK '.$uk;
        }

        return $sizes;
    }

    /**
     * @return array<string, string> hex code => name
     */
    protected function colours(): array
    {
        return [
            '#000000' => 'Black', '#ffffff' => 'White', '#808080' => 'Grey', '#1f2a44' => 'Navy Blue',
            '#2563eb' => 'Blue', '#7dd3fc' => 'Sky Blue', '#0f766e' => 'Teal', '#16a34a' => 'Green',
            '#6b7c2e' => 'Olive', '#a7f3d0' => 'Mint', '#facc15' => 'Yellow', '#d4a017' => 'Mustard',
            '#f97316' => 'Orange', '#ffcba4' => 'Peach', '#dc2626' => 'Red', '#7f1d1d' => 'Maroon',
            '#ec4899' => 'Pink', '#c026d3' => 'Magenta', '#7c3aed' => 'Purple', '#c4b5fd' => 'Lavender',
            '#7c4a2d' => 'Brown', '#e8d9b5' => 'Beige', '#fff8e1' => 'Cream', '#d4af37' => 'Gold',
            '#c0c0c0' => 'Silver', '#b7410e' => 'Rust', '#ff7f50' => 'Coral', '#722f37' => 'Wine',
            '#40e0d0' => 'Turquoise', 'multicolour' => 'Multicolour',
        ];
    }

    /**
     * @param  list<string>  $names
     * @return array<string, string>
     */
    protected function named(array $names): array
    {
        $options = [];
        foreach ($names as $name) {
            $options[Str::slug($name)] = $name;
        }

        return $options;
    }
}

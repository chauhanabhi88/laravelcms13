<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Default home page: hero (the CMS home's existing banner group), trust strip, category
     * tiles, promo banners, an "Under ₹199" rule collection, the CMS intro block, featured
     * articles and the "Picked for You" grid. Runs only on an empty home_section table;
     * sections without content (no banners, no products yet) hide themselves.
     */
    public function up(): void
    {
        if (! Schema::hasTable('home_section') || DB::table('home_section')->exists()) {
            return;
        }

        $now = now();
        $locales = $this->locales();

        $collectionId = DB::table('product_collection')->where('slug', 'under-199')->value('id');
        if ($collectionId === null) {
            $collectionId = DB::table('product_collection')->insertGetId([
                'slug' => 'under-199',
                'type' => 'rule',
                'rules' => json_encode(['max_price' => 199]),
                'sort' => 'popularity',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($locales as $locale) {
                DB::table('product_collection_translation')->insert([
                    'product_collection_id' => $collectionId,
                    'locale' => $locale,
                    'name' => 'Under ₹199',
                    'description' => 'Everyday finds at pocket-friendly prices.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $sections = [
            ['hero', ['banner_group_code' => 'home-banner'], null, null],
            ['trust_strip', [], null, null],
            ['category_tiles', ['category_ids' => []], 'Shop by Category', null],
            ['banner_row', ['banner_group_code' => 'home-promo'], null, null],
            ['collection', ['collection_id' => (int) $collectionId, 'limit' => 12], 'Under ₹199', 'Everyday finds at pocket-friendly prices'],
            ['html_block', ['block_code' => 'home_intro'], null, null],
            ['blog_posts', ['limit' => 3], 'From the Blog', null],
            ['products_for_you', [], 'Picked for You', null],
        ];

        foreach ($sections as $position => [$type, $config, $title, $subtitle]) {
            $id = DB::table('home_section')->insertGetId([
                'type' => $type,
                'config' => json_encode((object) $config),
                'sort_order' => ($position + 1) * 10,
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($locales as $locale) {
                DB::table('home_section_translation')->insert([
                    'home_section_id' => $id,
                    'locale' => $locale,
                    'title' => $title,
                    'subtitle' => $subtitle,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Sections may have been edited since; leave them (the table drop removes them).
    }

    /**
     * @return list<string>
     */
    private function locales(): array
    {
        $locales = Schema::hasTable('language') ? DB::table('language')->where('status', 1)->pluck('locale')->filter()->all() : [];

        return array_values(array_unique(array_merge([(string) config('app.locale', 'en')], array_map('strval', $locales))));
    }
};

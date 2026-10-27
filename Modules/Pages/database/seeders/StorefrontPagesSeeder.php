<?php

declare(strict_types=1);

namespace Modules\Pages\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Pages\Models\Pages;

/**
 * Placeholder CMS pages linked from the storefront header and footer (theme.footer.columns).
 *
 * Idempotent: a slug that already exists (even soft-deleted) is left untouched, so
 * content edited in the admin is never overwritten.
 */
class StorefrontPagesSeeder extends Seeder
{
    /**
     * @var array<string, string> slug => title
     */
    public const PAGES = [
        'about-us' => 'About us',
        'careers' => 'Careers',
        'sell-with-us' => 'Sell with Us',
        'grievance-officer' => 'Grievance Officer',
        'privacy-policy' => 'Privacy policy',
        'terms-and-conditions' => 'Terms of use',
        'returns-policy' => 'Returns & refunds',
        'shipping-policy' => 'Shipping',
        'cancellation-policy' => 'Cancellation',
        'faqs' => 'Frequently asked questions',
    ];

    public function run(): void
    {
        foreach (self::PAGES as $slug => $title) {
            if (Pages::withTrashed()->where('slug', $slug)->exists()) {
                continue;
            }

            Pages::create([
                'slug' => $slug,
                'status' => config('core.enabled'),
                'title' => $title,
                'body' => '<p>'.e($title).' details will be published here soon.</p>',
                'meta_title' => $title,
                'meta_description' => null,
            ]);
        }
    }
}

<?php

use Modules\Pages\Database\Seeders\StorefrontPagesSeeder;
use Modules\Pages\Models\Pages;

test('footer columns link only to published pages and existing routes', function () {
    Pages::factory()->create(['slug' => 'returns-policy', 'title' => 'Returns']);
    Pages::factory()->create(['slug' => 'privacy-policy', 'title' => 'Privacy']);
    Pages::factory()->disabled()->create(['slug' => 'careers', 'title' => 'Careers']);

    $this->get(frontRoute('homepage'))
        ->assertSuccessful()
        ->assertSee('href="'.frontRoute('page', ['returns-policy']).'"', false)
        ->assertSee('href="'.frontRoute('page', ['privacy-policy']).'"', false)
        ->assertSee('href="'.frontRoute('contact.index').'"', false)
        ->assertDontSee('href="'.frontRoute('page', ['careers']).'"', false)
        ->assertSee('Returns &amp; refunds', false)
        ->assertSee('Cash on Delivery');
});

test('the storefront pages seeder adds the missing placeholder pages without touching existing ones', function () {
    Pages::withTrashed()->whereIn('slug', array_keys(StorefrontPagesSeeder::PAGES))->forceDelete();
    $existing = Pages::factory()->create(['slug' => 'faqs', 'title' => 'Our own FAQ']);

    $this->seed(StorefrontPagesSeeder::class);
    $this->seed(StorefrontPagesSeeder::class);

    expect(Pages::whereIn('slug', array_keys(StorefrontPagesSeeder::PAGES))->count())
        ->toBe(count(StorefrontPagesSeeder::PAGES));
    expect($existing->fresh()->title)->toBe('Our own FAQ');

    $this->get(frontRoute('page', ['grievance-officer']))
        ->assertSuccessful()
        ->assertSee('Grievance Officer');
});

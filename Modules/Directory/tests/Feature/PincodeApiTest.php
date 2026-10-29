<?php

use Illuminate\Support\Facades\DB;
use Modules\Directory\Models\DirectoryPincode;

test('a known pincode returns its city, state and flags', function () {
    $stateId = DB::table('directory_state')->insertGetId(['country' => 'IN', 'code' => 'DL', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('directory_state_translation')->insert(['directory_country_state_id' => $stateId, 'name' => 'Delhi', 'locale' => app()->getLocale()]);
    $cityId = DB::table('directory_country_city')->insertGetId(['country' => 'IN', 'state' => 'DL', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('directory_country_city_translation')->insert(['directory_country_city_id' => $cityId, 'name' => 'New Delhi', 'locale' => app()->getLocale()]);

    DirectoryPincode::factory()->create([
        'pincode' => '110001',
        'district' => 'New Delhi',
        'state_id' => $stateId,
        'city_id' => $cityId,
        'zone' => 'metro',
    ]);

    $this->getJson('/api/v1/pincode/110001')
        ->assertSuccessful()
        ->assertExactJson([
            'success' => true,
            'code' => 200,
            'message' => 'OK',
            'data' => [
                'pincode' => '110001',
                'district' => 'New Delhi',
                'city' => ['id' => $cityId, 'name' => 'New Delhi'],
                'state' => ['id' => $stateId, 'name' => 'Delhi', 'code' => 'DL'],
                'is_serviceable' => true,
                'cod_allowed' => true,
                'zone' => 'metro',
            ],
        ]);
});

test('cod is never offered where the pincode is not serviceable', function () {
    DirectoryPincode::factory()->create(['pincode' => '560001', 'is_serviceable' => 2, 'cod_allowed' => 1]);

    $this->getJson('/api/v1/pincode/560001')
        ->assertSuccessful()
        ->assertJsonPath('data.is_serviceable', false)
        ->assertJsonPath('data.cod_allowed', false)
        ->assertJsonPath('data.state', null);
});

test('an unknown pincode returns the 404 envelope', function () {
    $this->getJson('/api/v1/pincode/999999')
        ->assertNotFound()
        ->assertExactJson(['success' => false, 'code' => 404, 'message' => trans('directory::directory_pincode.messages.not_found')]);
});

test('a malformed pincode does not match the route', function () {
    $this->getJson('/api/v1/pincode/12AB34')->assertNotFound()->assertJsonPath('success', false);
});

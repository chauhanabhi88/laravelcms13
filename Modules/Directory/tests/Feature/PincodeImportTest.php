<?php

use Illuminate\Support\Facades\DB;
use Modules\Directory\Models\DirectoryCountryCity;
use Modules\Directory\Models\DirectoryCountryState;
use Modules\Directory\Models\DirectoryPincode;

function importPincodeFixture(): void
{
    test()->artisan('directory:import-pincodes', ['path' => __DIR__.'/../Fixtures/pincodes.csv', '--country' => 'IN'])
        ->assertSuccessful();
}

function stateNamed(string $name): ?object
{
    return DB::table((new DirectoryCountryState)->getTable().' as s')
        ->join('directory_state_translation as t', 't.directory_country_state_id', '=', 's.id')
        ->where('s.country', 'IN')
        ->where('t.name', $name)
        ->first(['s.id', 's.code']);
}

test('rows are imported, invalid ones skipped', function () {
    importPincodeFixture();

    expect(DirectoryPincode::query()->count())->toBe(6)
        ->and(DirectoryPincode::query()->where('pincode', '110001')->count())->toBe(2)
        ->and(DirectoryPincode::query()->where('office_name', 'Broken BO')->exists())->toBeFalse();

    $office = DirectoryPincode::query()->where('office_name', 'Connaught Place SO')->sole();

    expect($office->district)->toBe('New Delhi')
        ->and($office->latitude)->toBe('28.6304000')
        ->and($office->is_serviceable)->toBe(1)
        ->and($office->cod_allowed)->toBe(1)
        ->and(DirectoryPincode::query()->where('office_name', 'New Delhi GPO')->value('latitude'))->toBeNull();
});

test('states are mapped by name and alias, and missing ones created', function () {
    importPincodeFixture();

    $delhi = stateNamed('Delhi');
    $odisha = stateNamed('Odisha');
    $dnh = stateNamed('Dadra and Nagar Haveli and Daman and Diu');

    expect($delhi->code)->toBe('DL')
        ->and($odisha->code)->toBe('OD')
        ->and($dnh->code)->toBe('DH')
        ->and(DirectoryPincode::query()->where('pincode', '751001')->value('state_id'))->toBe($odisha->id)
        ->and(DirectoryPincode::query()->where('pincode', '396230')->value('state_id'))->toBe($dnh->id);
});

test('an existing state is reused instead of duplicated', function () {
    $stateId = DB::table((new DirectoryCountryState)->getTable())->insertGetId(['country' => 'IN', 'code' => 'KA', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('directory_state_translation')->insert(['directory_country_state_id' => $stateId, 'name' => 'Karnataka', 'locale' => 'en']);

    importPincodeFixture();

    expect(DirectoryPincode::query()->where('pincode', '560001')->value('state_id'))->toBe($stateId)
        ->and(DB::table((new DirectoryCountryState)->getTable())->where('country', 'IN')->where('code', 'KA')->count())->toBe(1);
});

test('cities are created from the district once per state', function () {
    importPincodeFixture();

    $cityIds = DirectoryPincode::query()->where('pincode', '110001')->pluck('city_id')->unique();

    expect($cityIds)->toHaveCount(1);

    $city = DB::table((new DirectoryCountryCity)->getTable())->find($cityIds->first());

    expect($city->country)->toBe('IN')
        ->and($city->state)->toBe('DL')
        ->and(DB::table('directory_country_city_translation')->where('directory_country_city_id', $city->id)->value('name'))->toBe('New Delhi');
});

test('unknown states keep the row without state or city', function () {
    importPincodeFixture();

    $row = DirectoryPincode::query()->where('pincode', '400099')->sole();

    expect($row->state_id)->toBeNull()->and($row->city_id)->toBeNull()->and($row->latitude)->toBeNull();
});

test('a re-run is idempotent and keeps admin-managed flags', function () {
    importPincodeFixture();
    DirectoryPincode::query()->where('pincode', '110001')->update(['cod_allowed' => 2, 'zone' => 'metro']);
    $counts = fn () => [
        DirectoryPincode::query()->count(),
        DB::table((new DirectoryCountryState)->getTable())->count(),
        DB::table((new DirectoryCountryCity)->getTable())->count(),
    ];
    $before = $counts();

    importPincodeFixture();

    expect($counts())->toBe($before)
        ->and(DirectoryPincode::query()->where('pincode', '110001')->pluck('cod_allowed')->unique()->all())->toBe([2])
        ->and(DirectoryPincode::query()->where('pincode', '110001')->value('zone'))->toBe('metro');
});

test('a missing file or missing columns fail cleanly', function () {
    $this->artisan('directory:import-pincodes', ['path' => 'storage/app/does-not-exist.csv'])->assertFailed();

    $file = tempnam(sys_get_temp_dir(), 'pin');
    file_put_contents($file, "foo,bar\n1,2\n");

    $this->artisan('directory:import-pincodes', ['path' => $file])->assertFailed();

    unlink($file);
});

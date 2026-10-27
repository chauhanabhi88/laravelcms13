<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\Directory\Models\DirectoryPincode;
use Modules\Seller\Drivers\Bank\FakeBankVerifier;
use Modules\Seller\Models\Seller;
use Modules\Seller\Models\SellerUser;
use Modules\User\Models\User;

if (! function_exists('sellerAdmin')) {
    /**
     * An admin user whose role holds $permissions (null = every Seller permission).
     *
     * @param  list<string>|null  $permissions
     */
    function sellerAdmin(?array $permissions = null): User
    {
        $permissions ??= array_keys(array_merge(...array_values(require base_path('Modules/Seller/config/permissions.php'))));

        $roleId = DB::table('role')->insertGetId([
            'name' => 'Seller Ops',
            'slug' => 'seller_ops_'.uniqid(),
            'permissions' => json_encode($permissions),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->forceCreate([
            'name' => 'Seller Ops Admin',
            'email' => uniqid('seller-admin-').'@example.test',
            'password' => bcrypt('Secret#123'),
            'role_id' => $roleId,
            'status' => 1,
        ]);
    }

    /**
     * The id of a directory_state row with this ISO code (created when missing).
     */
    function sellerTestState(string $code = 'KA', string $name = 'Karnataka'): int
    {
        $id = DB::table('directory_state')->where('country', 'IN')->where('code', $code)->value('id');

        if ($id === null) {
            $id = DB::table('directory_state')->insertGetId(['country' => 'IN', 'code' => $code, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('directory_state_translation')->insert(['directory_country_state_id' => $id, 'name' => $name, 'locale' => app()->getLocale()]);
        }

        return (int) $id;
    }

    function sellerTestPincode(string $pincode = '560034', bool $serviceable = true): DirectoryPincode
    {
        $factory = DirectoryPincode::factory()->state(['pincode' => $pincode, 'district' => 'Bengaluru Urban', 'state_id' => sellerTestState()]);

        return ($serviceable ? $factory : $factory->unserviceable())->create();
    }

    /**
     * Switch bank verification to the scriptable fake driver.
     */
    function useFakeBankVerifier(): FakeBankVerifier
    {
        config(['seller.defaults.bank_verifier' => 'fake']);

        return app(FakeBankVerifier::class);
    }

    /**
     * Answer IFSC directory lookups: known codes return HDFC Koramangala, others 404.
     *
     * @param  list<string>  $known
     */
    function fakeIfscDirectory(array $known = ['HDFC0001234']): void
    {
        Http::fake([
            'ifsc.razorpay.com/*' => function ($request) use ($known) {
                $code = basename(parse_url($request->url(), PHP_URL_PATH));

                return in_array($code, $known, true)
                    ? Http::response(['BANK' => 'HDFC Bank', 'BRANCH' => 'Koramangala', 'CITY' => 'Bengaluru', 'STATE' => 'Karnataka', 'ADDRESS' => '80 Feet Road', 'IFSC' => $code])
                    : Http::response('Not Found', 404);
            },
        ]);
    }

    /**
     * A seller with an owner login, in the given factory state (default: active).
     *
     * @return array{0: Seller, 1: SellerUser}
     */
    function sellerWithOwner(string $state = 'active', array $userAttributes = []): array
    {
        $seller = Seller::factory()->{$state}()->create();
        $owner = SellerUser::factory()->for($seller)->create($userAttributes);

        return [$seller, $owner];
    }
}

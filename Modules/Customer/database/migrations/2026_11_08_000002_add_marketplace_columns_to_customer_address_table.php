<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indian address shape (M08): recipient, phone, house/flat, area/street, landmark and a
     * pincode that resolves city/state from the pincode master.
     *
     * The legacy columns (street_name, building, unit_no, postal_code) stay and are kept in
     * step by CustomerAddress::saving(), because the admin customer screen and the legacy API
     * still write them. Existing rows are backfilled and their tag normalised to home/work/other.
     * is_default_address keeps its 1/0 semantics (documented exception to 1/2).
     */
    public function up(): void
    {
        Schema::table('customer_address', function (Blueprint $table) {
            $table->string('name', 100)->nullable()->after('customer_id')->comment('Recipient');
            $table->string('phone', 15)->nullable()->after('name');
            $table->string('address_line1', 200)->nullable()->after('phone')->comment('House / flat / building');
            $table->string('address_line2', 200)->nullable()->after('address_line1')->comment('Area / street');
            $table->string('landmark', 120)->nullable()->after('address_line2');
            $table->char('pincode', 6)->nullable()->after('landmark')->index();
            $table->unsignedInteger('city_id')->nullable()->after('pincode');
            $table->string('city_name', 100)->nullable()->after('city_id');
            $table->unsignedInteger('state_id')->nullable()->after('city_name');
            $table->string('state_name', 100)->nullable()->after('state_id');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->index(['customer_id', 'is_default_address']);
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('customer_address', function (Blueprint $table) {
            $table->dropIndex(['customer_id', 'is_default_address']);
            $table->dropIndex(['pincode']);
            $table->dropColumn([
                'name', 'phone', 'address_line1', 'address_line2', 'landmark', 'pincode',
                'city_id', 'city_name', 'state_id', 'state_name', 'latitude', 'longitude',
            ]);
        });
    }

    private function backfill(): void
    {
        DB::table('customer_address')
            ->leftJoin('customer', 'customer.id', '=', 'customer_address.customer_id')
            ->orderBy('customer_address.id')
            ->select([
                'customer_address.id', 'customer_address.street_name', 'customer_address.building', 'customer_address.unit_no',
                'customer_address.postal_code', 'customer_address.tag', 'customer.first_name', 'customer.last_name', 'customer.phone',
            ])
            ->chunkById(500, function ($addresses): void {
                foreach ($addresses as $address) {
                    $pincode = trim((string) $address->postal_code);
                    $pincode = preg_match('/^[1-9][0-9]{5}$/', $pincode) === 1 ? $pincode : null;
                    $line1 = collect([$address->unit_no, $address->building])->map(fn ($part) => trim((string) $part))->filter()->implode(', ');
                    $tag = strtolower(trim((string) $address->tag));

                    DB::table('customer_address')->where('id', $address->id)->update(array_merge([
                        'name' => mb_substr(trim($address->first_name.' '.$address->last_name), 0, 100) ?: null,
                        'phone' => $address->phone,
                        'address_line1' => $line1 === '' ? null : mb_substr($line1, 0, 200),
                        'address_line2' => trim((string) $address->street_name) === '' ? null : mb_substr(trim((string) $address->street_name), 0, 200),
                        'pincode' => $pincode,
                        'tag' => in_array($tag, ['home', 'work'], true) ? $tag : 'other',
                    ], $this->place($pincode)));
                }
            }, 'customer_address.id', 'id');
    }

    /**
     * City/state from the pincode master, when the Directory pincode table is present.
     *
     * @return array<string, mixed>
     */
    private function place(?string $pincode): array
    {
        if ($pincode === null || ! Schema::hasTable('directory_pincode')) {
            return [];
        }

        $office = DB::table('directory_pincode')->where('pincode', $pincode)->orderBy('id')->first(['city_id', 'state_id', 'district']);

        if ($office === null) {
            return [];
        }

        return [
            'city_id' => $office->city_id,
            'state_id' => $office->state_id,
            'city_name' => $office->district ? mb_substr($office->district, 0, 100) : null,
        ];
    }
};

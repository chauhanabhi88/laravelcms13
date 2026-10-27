<?php

declare(strict_types=1);

namespace Modules\Seller\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Seller\Models\Seller;
use Modules\Seller\Models\SellerKycDocument;

/**
 * Database row only; tests that download it put a file at its path on the fake disk.
 *
 * @extends Factory<SellerKycDocument>
 */
class SellerKycDocumentFactory extends Factory
{
    protected $model = SellerKycDocument::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seller_id' => Seller::factory(),
            'type' => 'gst_certificate',
            'disk' => (string) config('seller.kyc_disk'),
            'path' => config('seller.kyc_folder').'/test/'.Str::uuid().'.pdf',
            'original_name' => 'gst-certificate.pdf',
            'mime' => 'application/pdf',
            'size' => 1024,
            'status' => SellerKycDocument::STATUS_PENDING,
        ];
    }
}

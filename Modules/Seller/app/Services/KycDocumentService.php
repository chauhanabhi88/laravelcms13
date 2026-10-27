<?php

declare(strict_types=1);

namespace Modules\Seller\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Modules\Seller\Models\Seller;
use Modules\Seller\Models\SellerKycDocument;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * KYC files on the private disk (config seller.kyc_disk) under seller_kyc/{seller_id}/
 * with random names. Types a seller holds one of replace the earlier upload.
 */
class KycDocumentService
{
    public function store(Seller $seller, string $type, UploadedFile $file): SellerKycDocument
    {
        if (! in_array($type, (array) config('seller.kyc_document_types'), true)) {
            throw new InvalidArgumentException("Unknown KYC document type [{$type}].");
        }

        $disk = (string) config('seller.kyc_disk', 'private');
        $path = $file->store(config('seller.kyc_folder').'/'.$seller->id, $disk);

        if ($path === false) {
            throw new \RuntimeException('The document could not be stored.');
        }

        // Remove the stored file again if the surrounding transaction rolls back.
        DB::afterRollBack(fn () => Storage::disk($disk)->delete($path));

        if (in_array($type, (array) config('seller.kyc_single_types'), true)) {
            $this->deleteOfType($seller, $type);
        }

        return SellerKycDocument::query()->create([
            'seller_id' => $seller->id,
            'type' => $type,
            'disk' => $disk,
            'path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime' => (string) ($file->getMimeType() ?: $file->getClientMimeType()),
            'size' => (int) $file->getSize(),
            'status' => SellerKycDocument::STATUS_PENDING,
        ]);
    }

    public function latestOfType(Seller $seller, string $type): ?SellerKycDocument
    {
        return SellerKycDocument::query()->where('seller_id', $seller->id)->where('type', $type)->latest('id')->first();
    }

    /**
     * Inline response for the browser (PDF / image preview); never a public URL.
     */
    public function response(SellerKycDocument $document): StreamedResponse
    {
        $storage = Storage::disk($document->disk);
        abort_unless($storage->exists($document->path), 404);

        return $storage->response($document->path, $document->original_name, [
            'Content-Type' => $document->mime,
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
        ], 'inline');
    }

    private function deleteOfType(Seller $seller, string $type): void
    {
        $old = SellerKycDocument::query()->where('seller_id', $seller->id)->where('type', $type)->get();

        foreach ($old as $document) {
            $document->delete();
            // Only drop the file once the replacement is committed.
            DB::afterCommit(fn () => Storage::disk($document->disk)->delete($document->path));
        }
    }
}

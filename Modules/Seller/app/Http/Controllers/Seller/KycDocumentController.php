<?php

declare(strict_types=1);

namespace Modules\Seller\Http\Controllers\Seller;

use Illuminate\Http\Request;
use Modules\Seller\Models\SellerKycDocument;
use Modules\Seller\Services\KycDocumentService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A seller's own KYC file, streamed from the private disk after the tenancy check.
 */
class KycDocumentController extends SellerPanelController
{
    public function show(Request $request, KycDocumentService $documents): StreamedResponse
    {
        $document = SellerKycDocument::query()->findOrFail((int) $request->route('document'));
        $this->authorizeOwned($document);

        return $documents->response($document);
    }
}

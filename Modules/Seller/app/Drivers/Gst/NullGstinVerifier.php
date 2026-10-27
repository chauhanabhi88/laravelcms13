<?php

declare(strict_types=1);

namespace Modules\Seller\Drivers\Gst;

use Modules\Seller\Contracts\GstinVerifier;
use Modules\Seller\Support\GstinVerificationResult;

/**
 * Default: no external lookup. Format, state code and checksum are validated by the Gstin
 * rule; the admin checks the uploaded GST certificate.
 */
class NullGstinVerifier implements GstinVerifier
{
    public function verify(string $gstin): GstinVerificationResult
    {
        return GstinVerificationResult::unchecked();
    }
}

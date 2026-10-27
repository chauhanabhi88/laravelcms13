<?php

declare(strict_types=1);

namespace Modules\Seller\Contracts;

use Modules\Seller\Support\GstinVerificationResult;

/**
 * Looks a GSTIN up with the tax authority through a third-party API (setting
 * seller.gst_verifier): "null" checks nothing beyond format and checksum, "http" calls
 * the configured API.
 */
interface GstinVerifier
{
    public function verify(string $gstin): GstinVerificationResult;
}

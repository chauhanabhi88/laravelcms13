<?php

declare(strict_types=1);

namespace Modules\Seller\Drivers\Gst;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Modules\Seller\Contracts\GstinVerifier;
use Modules\Seller\Support\GstinVerificationResult;

/**
 * Generic GSTIN API adapter: GET {url}/{GSTIN} with the key in an "x-api-key" header.
 *
 * Reads the legal name from "legal_name", "lgnm" or "data.lgnm" and the registration
 * status from "status", "sts" or "data.sts" ("Active" = active), which covers the common
 * GST Suvidha Provider response shapes. Any failure leaves the GSTIN unverified rather than
 * blocking onboarding.
 */
class HttpGstinVerifier implements GstinVerifier
{
    public function __construct(private readonly string $url, private readonly string $key) {}

    public function verify(string $gstin): GstinVerificationResult
    {
        if ($this->url === '') {
            return GstinVerificationResult::unchecked('GSTIN API URL is not configured.');
        }

        try {
            $response = Http::integration('gstin')
                ->withHeaders(['x-api-key' => $this->key])
                ->get(rtrim($this->url, '/').'/'.strtoupper($gstin));
        } catch (ConnectionException $e) {
            report($e);

            return GstinVerificationResult::unchecked($e->getMessage());
        }

        if (! $response->successful() || ! is_array($response->json())) {
            return GstinVerificationResult::unchecked('GSTIN API answered HTTP '.$response->status().'.');
        }

        $data = (array) $response->json();
        $legalName = $this->first($data, ['legal_name', 'lgnm', 'data.lgnm', 'data.legal_name']);
        $status = $this->first($data, ['status', 'sts', 'data.sts', 'data.status']);

        return new GstinVerificationResult(
            checked: $legalName !== null,
            legalName: $legalName,
            active: $status === null || strcasecmp($status, 'active') === 0,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keys
     */
    private function first(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = Arr::get($data, $key);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }
}

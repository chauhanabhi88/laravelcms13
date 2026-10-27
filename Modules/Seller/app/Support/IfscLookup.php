<?php

declare(strict_types=1);

namespace Modules\Seller\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Seller\Exceptions\IfscLookupUnavailable;
use Modules\Seller\Support\Validation\Ifsc;

/**
 * Bank and branch for an IFSC from the public IFSC directory
 * (GET https://ifsc.razorpay.com/{IFSC}). Hits are cached for 30 days, misses briefly.
 */
class IfscLookup
{
    private const MISS = '__missing__';

    /**
     * @return array{ifsc: string, bank: string, branch: string, city: string, state: string, address: string}|null
     *
     * @throws IfscLookupUnavailable when the directory cannot be reached
     */
    public function find(string $ifsc): ?array
    {
        $ifsc = strtoupper(trim($ifsc));

        if (! Ifsc::isValid($ifsc)) {
            return null;
        }

        $key = 'seller.ifsc.'.$ifsc;
        $cached = Cache::get($key);

        if ($cached === self::MISS) {
            return null;
        }

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = Http::integration('ifsc')->get(rtrim((string) config('seller.ifsc_lookup.url'), '/').'/'.$ifsc);
        } catch (ConnectionException $e) {
            throw new IfscLookupUnavailable($e->getMessage(), previous: $e);
        }

        if ($response->status() === 404) {
            Cache::put($key, self::MISS, now()->addMinutes((int) config('seller.ifsc_lookup.miss_cache_minutes', 60)));

            return null;
        }

        if (! $response->successful() || ! is_array($response->json())) {
            throw new IfscLookupUnavailable("IFSC directory answered HTTP {$response->status()}.");
        }

        $data = (array) $response->json();
        $details = [
            'ifsc' => $ifsc,
            'bank' => (string) ($data['BANK'] ?? ''),
            'branch' => (string) ($data['BRANCH'] ?? ''),
            'city' => (string) ($data['CITY'] ?? ''),
            'state' => (string) ($data['STATE'] ?? ''),
            'address' => (string) ($data['ADDRESS'] ?? ''),
        ];

        Cache::put($key, $details, now()->addDays((int) config('seller.ifsc_lookup.cache_days', 30)));

        return $details;
    }
}

<?php

declare(strict_types=1);

namespace Modules\Finance\Support;

use Illuminate\Support\Carbon;
use Modules\Logistics\Support\Shiprocket\ShiprocketClient;

/**
 * COD remittances from the courier aggregator's account API (plan M17 §5.7), flattened into
 * CodRemittanceService::import() rows (remittance_ref, remittance_date, utr, awb, amount).
 * The response is read defensively: remittances without per-AWB lines are skipped, and the
 * CSV upload remains the fallback.
 */
class ShiprocketRemittanceSource
{
    public function __construct(private readonly ?ShiprocketClient $client = null) {}

    /**
     * @return list<array<string, string>>
     */
    public function fetch(int $days = 15): array
    {
        $client = $this->client ?? ShiprocketClient::fromConfig();

        if (! $client->isConfigured()) {
            return [];
        }

        $body = $client->get('/v1/external/account/details/remittance', [
            'from' => Carbon::today()->subDays($days)->toDateString(),
            'to' => Carbon::today()->toDateString(),
        ]);

        $rows = [];

        foreach ((array) ($body['data'] ?? []) as $remittance) {
            $remittance = (array) $remittance;
            $ref = (string) ($remittance['crf_id'] ?? $remittance['remittance_id'] ?? $remittance['id'] ?? '');

            foreach ((array) ($remittance['awbs'] ?? $remittance['shipments'] ?? []) as $line) {
                $line = (array) $line;
                $awb = (string) ($line['awb'] ?? $line['awb_code'] ?? '');

                if ($ref === '' || $awb === '') {
                    continue;
                }

                $rows[] = [
                    'remittance_ref' => $ref,
                    'remittance_date' => (string) ($remittance['remittance_date'] ?? $remittance['date'] ?? ''),
                    'utr' => (string) ($remittance['utr'] ?? $remittance['utr_no'] ?? ''),
                    'awb' => $awb,
                    'amount' => (string) ($line['cod_amount'] ?? $line['amount'] ?? '0'),
                ];
            }
        }

        return $rows;
    }
}

<?php

declare(strict_types=1);

namespace Modules\Order\Http\Controllers\Seller\Concerns;

use Illuminate\Http\Request;
use Modules\Order\Repositories\SellerOrderDeskRepository;

/**
 * The order desk's grid state: tab, Ready-to-Ship sub-filter, search, SKU, payment, SLA and
 * date range. Values given on the request win and are kept in the session
 * (setFilterSession, key order.cache.seller_order_desk); otherwise the last ones are reused.
 */
trait ReadsDeskFilters
{
    private const FILTER_KEYS = ['tab', 'sub', 'q', 'sku', 'payment', 'sla', 'from', 'to'];

    /**
     * @return array{tab: string, sub: string|null, q: string, sku: string, payment: string|null, sla: string|null, from: string|null, to: string|null}
     */
    protected function deskFilters(Request $request): array
    {
        $sessionKey = (string) config('order.cache.seller_order_desk');
        $given = array_intersect_key($request->all(), array_flip(self::FILTER_KEYS));
        $session = (array) getSessionFilter($sessionKey, 'values');

        $values = match (true) {
            $request->boolean('reset') => ['tab' => $given['tab'] ?? ($session['tab'] ?? null)],
            $given === [] => $session,
            // A tab (or sub-filter) link keeps the search filters; the filter form sends them all.
            array_diff(array_keys($given), ['tab', 'sub']) === [] => array_merge($session, ['sub' => null], $given),
            default => $given,
        };

        $filters = [
            'tab' => array_key_exists((string) ($values['tab'] ?? ''), SellerOrderDeskRepository::TABS) ? (string) $values['tab'] : SellerOrderDeskRepository::TAB_PENDING,
            'sub' => in_array($values['sub'] ?? null, [SellerOrderDeskRepository::SUB_LABEL_PENDING, SellerOrderDeskRepository::SUB_AWAITING_PICKUP], true) ? (string) $values['sub'] : null,
            'q' => mb_substr(trim((string) ($values['q'] ?? '')), 0, 40),
            'sku' => mb_substr(trim((string) ($values['sku'] ?? '')), 0, 60),
            'payment' => in_array($values['payment'] ?? null, ['cod', 'prepaid'], true) ? (string) $values['payment'] : null,
            'sla' => in_array($values['sla'] ?? null, [SellerOrderDeskRepository::SLA_ON_TIME, SellerOrderDeskRepository::SLA_AT_RISK, SellerOrderDeskRepository::SLA_BREACHED], true) ? (string) $values['sla'] : null,
            'from' => is_string($values['from'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $values['from']) ? $values['from'] : null,
            'to' => is_string($values['to'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $values['to']) ? $values['to'] : null,
        ];

        setFilterSession($sessionKey, $filters, 'values');

        return $filters;
    }
}

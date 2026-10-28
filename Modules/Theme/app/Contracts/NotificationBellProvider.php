<?php

declare(strict_types=1);

namespace Modules\Theme\Contracts;

/**
 * In-app notification bell of the storefront header and the supplier panel topbar.
 * Rebound by the Notification module (M09); null hides the bell.
 *
 * The array: count (unread), feed_url (JSON {data: {unread, items}}), index_url,
 * read_url (with the placeholder __ID__), read_all_url, poll_seconds.
 */
interface NotificationBellProvider
{
    /**
     * @return array{count: int, feed_url: string, index_url: string, read_url: string, read_all_url: string, poll_seconds: int}|null
     */
    public function forCustomer(): ?array;

    /**
     * @return array{count: int, feed_url: string, index_url: string, read_url: string, read_all_url: string, poll_seconds: int}|null
     */
    public function forSeller(): ?array;
}

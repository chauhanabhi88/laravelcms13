<?php

declare(strict_types=1);

namespace Modules\Notification\Http\Controllers\Seller;

use Illuminate\Database\Eloquent\Model;
use Modules\Notification\Http\Controllers\Concerns\InteractsWithInbox;
use Modules\Seller\Http\Controllers\Seller\SellerPanelController;

/**
 * Seller user inbox (FR-NTF-005): /supplier/notifications, the topbar bell feed and mark as
 * read. Each staff login has its own inbox.
 */
class InboxController extends SellerPanelController
{
    use InteractsWithInbox;

    protected function inboxOwner(): Model
    {
        return $this->sellerUser();
    }

    protected function inboxView(): string
    {
        return 'notification::seller.inbox.index';
    }

    protected function inboxRoute(string $action): string
    {
        return frontRoute('seller.notifications.'.$action);
    }
}

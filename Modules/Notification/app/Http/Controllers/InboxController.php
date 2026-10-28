<?php

declare(strict_types=1);

namespace Modules\Notification\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Modules\Notification\Http\Controllers\Concerns\InteractsWithInbox;

/**
 * Customer inbox (FR-NTF-005): /customer/notifications, the bell feed and mark as read.
 */
class InboxController extends Controller
{
    use InteractsWithInbox;

    protected function inboxOwner(): Model
    {
        $customer = Auth::guard('customer')->user();
        abort_unless($customer instanceof Model, 401);

        return $customer;
    }

    protected function inboxView(): string
    {
        return 'notification::frontend.inbox.index';
    }

    protected function inboxRoute(string $action): string
    {
        return frontRoute('customer.notifications.'.$action);
    }
}

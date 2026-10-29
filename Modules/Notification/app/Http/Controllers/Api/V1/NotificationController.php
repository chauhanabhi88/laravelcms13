<?php

declare(strict_types=1);

namespace Modules\Notification\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Modules\Core\Http\Controllers\Api\ApiController;
use Modules\Customer\Models\Customer;
use Modules\Notification\Services\InboxService;

/**
 * Customer in-app notifications over the API: GET /customer/notifications (paginated, with
 * the unread count), POST /customer/notifications/{id}/read, POST /customer/notifications/read-all.
 */
class NotificationController extends ApiController
{
    public function __construct(private readonly InboxService $inbox) {}

    public function index(Request $request): JsonResponse
    {
        $customer = $this->customer($request);
        $perPage = $this->perPage((int) config('notification.bell.per_page', 20));
        $present = fn (array $items): array => array_map(fn (DatabaseNotification $notification): array => $this->inbox->present($notification), $items);

        if ($request->has('cursor')) {
            $page = $customer->notifications()->latest()->orderByDesc('id')->cursorPaginate($perPage);

            return $this->ok($present($page->items()), ['unread' => $this->inbox->unreadCount($customer)] + $this->pagination($page));
        }

        $page = $this->inbox->paginate($customer, $perPage, (int) $request->query('page', 1));

        return $this->ok($present($page->items()), [
            'unread' => $this->inbox->unreadCount($customer),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
        ]);
    }

    public function read(Request $request): JsonResponse
    {
        $customer = $this->customer($request);
        $notification = $this->inbox->markRead($customer, (string) $request->route('id'));

        if ($notification === null) {
            return $this->fail(404, trans('notification::front.inbox.not_found'));
        }

        return $this->ok($this->inbox->present($notification), ['unread' => $this->inbox->unreadCount($customer)]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $updated = $this->inbox->markAllRead($this->customer($request));

        return $this->ok(['updated' => $updated], ['unread' => 0]);
    }

    protected function customer(Request $request): Customer
    {
        /** @var Customer */
        return $request->user('api');
    }
}

<?php

declare(strict_types=1);

namespace Modules\Notification\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Modules\Customer\Models\Customer;
use Modules\Notification\Services\InboxService;

/**
 * Customer in-app notifications over the API: GET /customer/notifications (paginated, with
 * the unread count), POST /customer/notifications/{id}/read, POST /customer/notifications/read-all.
 */
class NotificationController extends Controller
{
    public function __construct(private readonly InboxService $inbox) {}

    public function index(Request $request): JsonResponse
    {
        $customer = $this->customer($request);
        $perPage = min(50, max(1, (int) $request->query('per_page', (int) config('notification.bell.per_page', 20))));
        $page = $this->inbox->paginate($customer, $perPage, (int) $request->query('page', 1));

        return response()->json([
            'success' => true,
            'data' => array_map(fn (DatabaseNotification $notification): array => $this->inbox->present($notification), $page->items()),
            'meta' => [
                'unread' => $this->inbox->unreadCount($customer),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function read(Request $request): JsonResponse
    {
        $customer = $this->customer($request);
        $notification = $this->inbox->markRead($customer, (string) $request->route('id'));

        if ($notification === null) {
            return response()->json([
                'success' => false,
                'message' => trans('notification::front.inbox.not_found'),
                'code' => '404',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->inbox->present($notification),
            'meta' => ['unread' => $this->inbox->unreadCount($customer)],
        ]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $updated = $this->inbox->markAllRead($this->customer($request));

        return response()->json(['success' => true, 'data' => ['updated' => $updated], 'meta' => ['unread' => 0]]);
    }

    protected function customer(Request $request): Customer
    {
        /** @var Customer */
        return $request->user('api');
    }
}

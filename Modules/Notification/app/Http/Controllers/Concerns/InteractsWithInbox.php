<?php

declare(strict_types=1);

namespace Modules\Notification\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;
use Modules\Notification\Services\InboxService;

/**
 * Inbox actions shared by the customer and seller controllers: list, bell feed, mark one /
 * all as read. Another recipient's notification is a 404.
 */
trait InteractsWithInbox
{
    abstract protected function inboxOwner(): Model;

    abstract protected function inboxView(): string;

    abstract protected function inboxRoute(string $action): string;

    public function index(Request $request): View
    {
        $owner = $this->inboxOwner();
        $page = $this->inbox()->paginate($owner, null, (int) $request->query('page', 1));
        $page->withPath($this->inboxRoute('index'));

        return view($this->inboxView(), [
            'notifications' => $page,
            'items' => array_map(fn (DatabaseNotification $notification): array => $this->inbox()->present($notification), $page->items()),
            'unreadCount' => $this->inbox()->unreadCount($owner),
            'readAllUrl' => $this->inboxRoute('read_all'),
        ]);
    }

    public function feed(): JsonResponse
    {
        $owner = $this->inboxOwner();

        return response()->json([
            'success' => true,
            'data' => [
                'unread' => $this->inbox()->unreadCount($owner),
                'items' => $this->inbox()->latest($owner),
            ],
        ]);
    }

    public function read(Request $request): JsonResponse|RedirectResponse
    {
        $owner = $this->inboxOwner();
        $notification = $this->inbox()->markRead($owner, (string) $request->route('id'));

        abort_if($notification === null, 404);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => ['unread' => $this->inbox()->unreadCount($owner)]]);
        }

        $url = $this->inbox()->present($notification)['url'];

        return $url !== '' ? redirect()->to($url) : redirect()->to($this->inboxRoute('index'));
    }

    public function readAll(Request $request): JsonResponse|RedirectResponse
    {
        $updated = $this->inbox()->markAllRead($this->inboxOwner());

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => ['unread' => 0, 'updated' => $updated]]);
        }

        return redirect()->to($this->inboxRoute('index'))->with('success', trans('notification::front.inbox.all_read'));
    }

    protected function inbox(): InboxService
    {
        return app(InboxService::class);
    }
}

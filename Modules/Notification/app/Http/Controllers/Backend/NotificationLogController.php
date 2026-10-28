<?php

declare(strict_types=1);

namespace Modules\Notification\Http\Controllers\Backend;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Notification\Models\NotificationLog;
use Modules\Notification\Repositories\NotificationLogRepository;
use Modules\Notification\Services\NotificationHub;
use Modules\Notification\Support\NotificationOptions;

/**
 * Delivery log (FR-NTF-004): grid with filters, detail, and retry of failed / skipped rows.
 */
class NotificationLogController extends BackendController
{
    public function __construct(private NotificationLogRepository $logs)
    {
        parent::__construct();
    }

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('notification.cache.notification_log'), $request->input('per_page'))]);

            return view('notification::backend.notification_log.index', $this->gridData($request, getActiveMenuId($request)));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function filters(Request $request): JsonResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('notification.cache.notification_log'), $request->input('per_page'))]);
            setFilterSession(config('notification.cache.notification_log'), $request);

            $content = view('notification::backend.notification_log.partials.grid', $this->gridData($request, getActiveMenuId($request, 'admin.notification_log.index')));

            return response()->json([
                'type' => 'success',
                'content' => ['element' => 'collection', 'html' => $content->render()],
                'message' => $request->input('message'),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function show(Request $request): View|RedirectResponse
    {
        try {
            $log = $this->findOrFail($request->route('id'));
            $log->loadMissing('notifiable');

            return view('notification::backend.notification_log.show', [
                'log' => $log,
                'channels' => NotificationOptions::channels(),
                'statuses' => NotificationOptions::logStatuses(),
                'related' => $log->notification_id
                    ? NotificationLog::query()->where('notification_id', $log->notification_id)->whereKeyNot($log->id)->orderBy('id')->get()
                    : collect(),
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('admin.notification_log.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function retry(Request $request, NotificationHub $hub): RedirectResponse
    {
        try {
            $retry = $hub->retry($this->findOrFail($request->route('id')));

            return redirect()->route('admin.notification_log.show', updateUrlParams([$retry->id]))
                ->with('success', trans('notification::notification_log.messages.retried', ['status' => trans('notification::notification_log.statuses.'.$retry->status)]));
        } catch (\Throwable $e) {
            return redirect()->route('admin.notification_log.show', updateUrlParams([(int) $request->route('id')]))->with('error', $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function gridData(Request $request, mixed $menuId): array
    {
        $options = [
            'events' => NotificationOptions::events(),
            'channels' => NotificationOptions::channels(),
            'statuses' => NotificationOptions::logStatuses(),
        ];

        return [
            'request' => $request,
            'activeMenuId' => $menuId,
            'collection' => $this->logs->pagination($request),
            'columns' => getColumnObject()->getColumns($menuId),
            'filters' => $this->logs->getFilters($request, $options),
        ] + $options;
    }

    private function findOrFail(mixed $id): NotificationLog
    {
        $log = $id ? $this->logs->find((int) $id) : null;

        if (! $log instanceof NotificationLog) {
            throw new \RuntimeException(trans('notification::notification_log.messages.data_invalid'));
        }

        return $log;
    }
}

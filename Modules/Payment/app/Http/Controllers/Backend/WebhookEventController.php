<?php

declare(strict_types=1);

namespace Modules\Payment\Http\Controllers\Backend;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Payment\Jobs\ProcessWebhookEvent;
use Modules\Payment\Models\WebhookEvent;
use Modules\Payment\Repositories\WebhookEventRepository;
use Modules\Payment\Support\PaymentOptions;

/**
 * Webhook log with replay (FR-PAY-007). Replay puts a signed event back to "received" and
 * dispatches ProcessWebhookEvent again; the handlers are idempotent, so replaying a
 * processed event changes nothing.
 */
class WebhookEventController extends BackendController
{
    public function __construct(private WebhookEventRepository $webhookEvents)
    {
        parent::__construct();
    }

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('payment.cache.webhook_event'), $request->input('per_page'))]);

            return view('payment::backend.webhook_event.index', $this->gridData($request, getActiveMenuId($request)));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function filters(Request $request): JsonResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('payment.cache.webhook_event'), $request->input('per_page'))]);
            setFilterSession(config('payment.cache.webhook_event'), $request);

            $content = view('payment::backend.webhook_event.partials.grid', $this->gridData($request, getActiveMenuId($request, 'admin.webhook_event.index')));

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
            return view('payment::backend.webhook_event.show', [
                'event' => $this->findEvent($request),
                'statuses' => PaymentOptions::webhookStatuses(),
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('admin.webhook_event.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function replay(Request $request): RedirectResponse
    {
        try {
            $event = $this->findEvent($request);

            if (! $event->isReplayable()) {
                throw new \RuntimeException(trans('payment::webhook_event.messages.not_replayable'));
            }

            $event->forceFill(['status' => WebhookEvent::STATUS_RECEIVED, 'error' => null])->save();

            try {
                ProcessWebhookEvent::dispatch($event->id);
            } catch (\Throwable $e) {
                report($e);
            }

            $event->refresh();

            return redirect()->back()->with(
                $event->status === WebhookEvent::STATUS_FAILED ? 'error' : 'success',
                trans('payment::webhook_event.messages.replayed', ['status' => PaymentOptions::webhookStatuses()[$event->status] ?? $event->status]),
            );
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    private function findEvent(Request $request): WebhookEvent
    {
        $event = $this->webhookEvents->find((int) $request->route('id'));

        if (! $event instanceof WebhookEvent) {
            throw new \RuntimeException(trans('payment::webhook_event.messages.not_found'));
        }

        return $event;
    }

    /**
     * @return array<string, mixed>
     */
    private function gridData(Request $request, mixed $menuId): array
    {
        $options = [
            'providers' => PaymentOptions::webhookProviders(),
            'statuses' => PaymentOptions::webhookStatuses(),
        ];

        return [
            'request' => $request,
            'activeMenuId' => $menuId,
            'collection' => $this->webhookEvents->pagination($request),
            'columns' => getColumnObject()->getColumns($menuId),
            'filters' => $this->webhookEvents->getFilters($request, $options),
        ] + $options;
    }
}

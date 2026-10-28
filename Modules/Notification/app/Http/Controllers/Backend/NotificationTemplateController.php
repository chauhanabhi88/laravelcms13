<?php

declare(strict_types=1);

namespace Modules\Notification\Http\Controllers\Backend;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Notification\Http\Requests\NotificationTemplateRequest;
use Modules\Notification\Models\NotificationLog;
use Modules\Notification\Models\NotificationTemplate;
use Modules\Notification\Repositories\NotificationTemplateRepository;
use Modules\Notification\Services\TemplateTestSender;
use Modules\Notification\Support\EventCatalogue;
use Modules\Notification\Support\NotificationOptions;
use Modules\Notification\Support\TemplateRenderer;

/**
 * SMS / push / in-app / WhatsApp templates: grid, form (body, provider template id,
 * status), test send. Email templates are edited in the Mail module.
 */
class NotificationTemplateController extends BackendController
{
    public function __construct(private NotificationTemplateRepository $templates)
    {
        parent::__construct();
    }

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('notification.cache.notification_template'), $request->input('per_page'))]);

            return view('notification::backend.notification_template.index', $this->gridData($request, getActiveMenuId($request)));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function filters(Request $request): JsonResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('notification.cache.notification_template'), $request->input('per_page'))]);
            setFilterSession(config('notification.cache.notification_template'), $request);

            $content = view('notification::backend.notification_template.partials.grid', $this->gridData($request, getActiveMenuId($request, 'admin.notification_template.index')));

            return response()->json([
                'type' => 'success',
                'content' => ['element' => 'collection', 'html' => $content->render()],
                'message' => $request->input('message'),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function create(): View|RedirectResponse
    {
        try {
            return view('notification::backend.notification_template.create', $this->formData());
        } catch (\Throwable $e) {
            return redirect()->route('admin.notification_template.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function store(NotificationTemplateRequest $request): RedirectResponse
    {
        try {
            $template = $this->templates->create($this->payload($request));

            if ($request->input('snc')) {
                return redirect()->route('admin.notification_template.edit', updateUrlParams([$template->id]))->with('success', trans('notification::notification_template.messages.created_success'));
            }

            return redirect()->route('admin.notification_template.index', updateUrlParams())->with('success', trans('notification::notification_template.messages.created_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.notification_template.create', updateUrlParams())->withInput()->with('error', $e->getMessage());
        }
    }

    public function edit(Request $request): View|RedirectResponse
    {
        try {
            $template = $this->findOrFail($request->route('id'));

            return view('notification::backend.notification_template.edit', $this->formData($template) + [
                'notificationTemplate' => $template,
                'recentTests' => NotificationLog::query()
                    ->where('event_key', $template->event_key)
                    ->where('channel', $template->channel)
                    ->where('payload->template_id', $template->id)
                    ->latest('id')
                    ->limit(5)
                    ->get(),
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('admin.notification_template.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function update(NotificationTemplateRequest $request): RedirectResponse
    {
        $id = (int) $request->route('id');

        try {
            $this->templates->update($this->findOrFail($id), $this->payload($request));

            if ($request->input('snc')) {
                return redirect()->route('admin.notification_template.edit', updateUrlParams([$id]))->with('success', trans('notification::notification_template.messages.updated_success'));
            }

            return redirect()->route('admin.notification_template.index', updateUrlParams())->with('success', trans('notification::notification_template.messages.updated_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.notification_template.edit', updateUrlParams([$id]))->withInput()->with('error', $e->getMessage());
        }
    }

    public function updateStatus(Request $request): JsonResponse
    {
        if ($request->input('id') && ($template = $this->templates->find($request->input('id')))) {
            $status = (int) $request->input('status') === (int) config('core.enabled') ? config('core.enabled') : config('core.disabled');
            $this->templates->update($template, ['status' => $status]);
        }

        $refresh = new Request(['active_menu_id' => $request->input('active_menu_id')]);
        $refresh->merge(['message' => trans('core::core.messages.status_change_success')]);

        return $this->filters($refresh);
    }

    public function testSend(Request $request, TemplateTestSender $sender): RedirectResponse
    {
        $id = (int) $request->route('id');
        $validated = $request->validate(['destination' => ['required', 'string', 'max:255']]);

        try {
            $log = $sender->send($this->findOrFail($id), $validated['destination']);

            return redirect()->route('admin.notification_template.edit', updateUrlParams([$id]))->with(
                $log->status === NotificationLog::STATUS_SENT ? 'success' : 'error',
                $log->status === NotificationLog::STATUS_SENT
                    ? trans('notification::notification_template.messages.test_sent', ['destination' => $log->destination])
                    : trans('notification::notification_template.messages.test_failed', ['error' => $log->error]),
            );
        } catch (InvalidArgumentException $e) {
            return redirect()->route('admin.notification_template.edit', updateUrlParams([$id]))->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return redirect()->route('admin.notification_template.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function delete(Request $request): RedirectResponse
    {
        try {
            $this->templates->deleteRecord($request);

            return redirect()->route('admin.notification_template.index', updateUrlParams())->with('success', trans('notification::notification_template.messages.deleted_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.notification_template.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function massDelete(Request $request): RedirectResponse
    {
        try {
            $this->templates->destroyMultiple($request);

            return redirect()->route('admin.notification_template.index', updateUrlParams())->with('success', trans('notification::notification_template.messages.deleted_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.notification_template.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function gridData(Request $request, mixed $menuId): array
    {
        $options = $this->options();

        return [
            'request' => $request,
            'activeMenuId' => $menuId,
            'collection' => $this->templates->pagination($request),
            'columns' => getColumnObject()->getColumns($menuId),
            'filters' => $this->templates->getFilters($request, $options + ['statuses' => $this->templates->getStatusOptions(true)]),
            'statusOptions' => $this->templates->getStatusOptions(),
        ] + $options;
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?NotificationTemplate $template = null): array
    {
        $eventKey = old('event_key', $template?->event_key);

        return $this->options() + [
            'statusOptions' => $this->templates->getStatusOptions(),
            'catalogue' => config('notification.events'),
            'placeholders' => $eventKey && app(EventCatalogue::class)->has($eventKey)
                ? array_merge(['app_name', 'recipient_name', 'url'], app(EventCatalogue::class)->get($eventKey)->vars)
                : [],
            'usedPlaceholders' => $template ? app(TemplateRenderer::class)->placeholders($template->title.' '.$template->body) : [],
        ];
    }

    /**
     * @return array{events: array<string, string>, channels: array<string, string>, audiences: array<string, string>}
     */
    private function options(): array
    {
        return [
            'events' => NotificationOptions::events(),
            'channels' => NotificationOptions::channels(false),
            'audiences' => NotificationOptions::audiences(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(NotificationTemplateRequest $request): array
    {
        $validated = $request->validated();

        return [
            'event_key' => $validated['event_key'],
            'channel' => $validated['channel'],
            'audience' => $validated['audience'],
            'title' => filled($validated['title'] ?? null) ? trim($validated['title']) : null,
            'body' => trim($validated['body']),
            'provider_template_id' => filled($validated['provider_template_id'] ?? null) ? trim($validated['provider_template_id']) : null,
            'status' => (int) $validated['status'],
        ];
    }

    private function findOrFail(mixed $id): NotificationTemplate
    {
        $template = $id ? $this->templates->find((int) $id) : null;

        if (! $template instanceof NotificationTemplate) {
            throw new \RuntimeException(trans('notification::notification_template.messages.data_invalid'));
        }

        return $template;
    }
}

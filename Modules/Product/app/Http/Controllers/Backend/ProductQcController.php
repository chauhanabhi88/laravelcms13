<?php

declare(strict_types=1);

namespace Modules\Product\Http\Controllers\Backend;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Exceptions\InvalidStateTransition;
use Modules\Product\Exceptions\ProductException;
use Modules\Product\Http\Requests\Backend\ProductQcBulkRequest;
use Modules\Product\Http\Requests\Backend\ProductQcDecisionRequest;
use Modules\Product\Models\Product;
use Modules\Product\Repositories\ProductRepository;
use Modules\Product\Services\ProductModerationService;
use Modules\Product\Support\DuplicateDetector;
use Modules\Product\Support\ProductPresenter;
use Modules\Product\Support\ProductSettings;

/**
 * Catalog QC queue (FR-PRD-006): new submissions and published products with pending
 * changes, oldest first; split-pane review with the pending diff, approve / reject with
 * reason templates, and bulk decisions from the grid.
 */
class ProductQcController extends ProductAdminController
{
    public function __construct(
        ProductRepository $products,
        ProductPresenter $presenter,
        DuplicateDetector $duplicates,
        private readonly ProductModerationService $moderation,
    ) {
        parent::__construct($products, $presenter, $duplicates);
    }

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('product.cache.product_qc'), $request->input('per_page'))]);

            return view('product::backend.product_qc.index', $this->gridData($request, ProductRepository::GRID_QC, getActiveMenuId($request)) + [
                'reasonTemplates' => $this->reasonTemplates(),
                'slaHours' => ProductSettings::qcSlaHours(),
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function filters(Request $request): JsonResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('product.cache.product_qc'), $request->input('per_page'))]);
            setFilterSession(config('product.cache.product_qc'), $request);

            $content = view('product::backend.product_qc.partials.grid', $this->gridData(
                $request,
                ProductRepository::GRID_QC,
                getActiveMenuId($request, 'admin.product_qc.index'),
            ) + ['slaHours' => ProductSettings::qcSlaHours()]);

            return response()->json([
                'type' => 'success',
                'content' => ['element' => 'collection', 'html' => $content->render()],
                'message' => $request->input('message'),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Split view: the queue on the left, the product with its pending diff on the right.
     */
    public function review(Request $request): View|RedirectResponse
    {
        try {
            $product = $this->findOrFail($request->route('id'));
            $queue = $this->products->qcQueue((int) config('product.qc_queue_preview', 50));
            $position = $queue->search(fn (Product $queued): bool => $queued->id === $product->id);

            return view('product::backend.product_qc.review', $this->detailData($product) + [
                'queue' => $queue,
                'previousId' => $position !== false && $position > 0 ? $queue[$position - 1]->id : null,
                'nextId' => $position !== false ? $queue->get($position + 1)?->id : $queue->first()?->id,
                'slaHours' => ProductSettings::qcSlaHours(),
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('admin.product_qc.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function approve(ProductQcDecisionRequest $request): RedirectResponse
    {
        $id = (int) $request->route('id');

        try {
            $this->moderation->approve($this->findOrFail($id), $this->admin(), $request->reason());
        } catch (ProductException|InvalidStateTransition $e) {
            return redirect()->route('admin.product_qc.review', updateUrlParams([$id]))->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('admin.product_qc.review', updateUrlParams([$id]))->with('error', $e->getMessage());
        }

        return $this->toNextInQueue(trans('product::product.messages.approved'));
    }

    public function reject(ProductQcDecisionRequest $request): RedirectResponse
    {
        $id = (int) $request->route('id');

        try {
            $this->moderation->reject($this->findOrFail($id), $this->admin(), $request->reason(), $request->reasonCodes());
        } catch (ProductException|InvalidStateTransition $e) {
            return redirect()->route('admin.product_qc.review', updateUrlParams([$id]))->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('admin.product_qc.review', updateUrlParams([$id]))->withInput()->with('error', $e->getMessage());
        }

        return $this->toNextInQueue(trans('product::product.messages.rejected'));
    }

    public function bulk(ProductQcBulkRequest $request): RedirectResponse|JsonResponse
    {
        try {
            $result = $request->validated('action') === 'approve'
                ? $this->moderation->bulkApprove($request->ids(), $this->admin())
                : $this->moderation->bulkReject($request->ids(), $this->admin(), $request->reason(), $request->reasonCodes());
        } catch (ProductException $e) {
            return $this->bulkResponse($request, 'error', $e->getMessage());
        }

        $message = trans('product::product.messages.bulk_done', ['done' => count($result['done']), 'failed' => count($result['failed'])]);

        if ($result['failed'] !== []) {
            $details = collect($result['failed'])->map(fn (string $error, int $id): string => '#'.$id.': '.$error)->take(5)->implode(' ');

            return $this->bulkResponse($request, $result['done'] === [] ? 'error' : 'warning', $message.' '.$details);
        }

        return $this->bulkResponse($request, 'success', $message);
    }

    /**
     * After a decision, open the next product still waiting, or the queue when it is empty.
     */
    private function toNextInQueue(string $message): RedirectResponse
    {
        $next = $this->products->qcQueue(1)->first();

        if ($next !== null) {
            return redirect()->route('admin.product_qc.review', updateUrlParams([$next->id]))->with('success', $message);
        }

        return redirect()->route('admin.product_qc.index', updateUrlParams())->with('success', $message);
    }

    private function bulkResponse(Request $request, string $type, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['type' => $type, 'message' => $message], $type === 'error' ? 422 : 200);
        }

        return redirect()->route('admin.product_qc.index', updateUrlParams())->with($type === 'success' ? 'success' : 'error', $message);
    }

    /**
     * @return array<string, string>
     */
    private function reasonTemplates(): array
    {
        return collect((array) config('product.qc_reason_templates'))
            ->mapWithKeys(fn (string $code): array => [$code => trans('product::product.reason_templates.'.$code)])
            ->all();
    }
}

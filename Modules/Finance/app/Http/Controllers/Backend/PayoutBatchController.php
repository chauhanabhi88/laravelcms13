<?php

declare(strict_types=1);

namespace Modules\Finance\Http\Controllers\Backend;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Finance\Exceptions\FinanceException;
use Modules\Finance\Http\Controllers\Backend\Concerns\RendersFinanceGrid;
use Modules\Finance\Jobs\ProcessPayoutBatch;
use Modules\Finance\Models\PayoutBatch;
use Modules\Finance\Services\LedgerReleaseService;
use Modules\Finance\Services\PayoutService;
use Modules\Finance\Support\CsvExport;
use Modules\Finance\Support\FinanceOptions;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Payout batches (FR-FIN-004, plan M17 §5.3): build the cycle's draft, approve it (a different
 * admin than the builder), process it through the payout gateway; for the manual gateway
 * download the bank file and upload UTRs.
 */
class PayoutBatchController extends BackendController
{
    use RendersFinanceGrid;

    protected const GRID = 'payout_batch';

    protected const VIEW = 'payout_batch';

    public function __construct(private readonly PayoutService $payouts)
    {
        parent::__construct();
    }

    public function index(Request $request): View|RedirectResponse
    {
        return $this->renderIndex($request);
    }

    public function show(Request $request): View|RedirectResponse
    {
        $batch = $this->findBatch($request);

        if ($batch === null) {
            return redirect()->route('admin.payout_batch.index', updateUrlParams())->with('error', trans('finance::finance.messages.not_found'));
        }

        $batch->load(['payouts.seller', 'payouts.bankAccount', 'creator', 'approver']);

        return view('finance::backend.payout_batch.show', [
            'batch' => $batch,
            'batchStatuses' => FinanceOptions::batchStatuses(),
            'payoutStatuses' => FinanceOptions::payoutStatuses(),
        ]);
    }

    public function build(Request $request, LedgerReleaseService $release): RedirectResponse
    {
        try {
            $release->run();
            $batch = $this->payouts->build(null, $request->user());
        } catch (FinanceException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        if ($batch === null) {
            return redirect()->route('admin.payout_batch.index', updateUrlParams())->with('error', trans('finance::payout_batch.messages.nothing_payable'));
        }

        return redirect()->route('admin.payout_batch.show', updateUrlParams([$batch->id]))->with('success', trans('finance::payout_batch.messages.built', ['batch' => $batch->batch_no]));
    }

    public function approve(Request $request): RedirectResponse
    {
        return $this->act($request, fn (PayoutBatch $batch) => $this->payouts->approve($batch, $request->user()), 'approved');
    }

    public function process(Request $request): RedirectResponse
    {
        return $this->act($request, function (PayoutBatch $batch): void {
            if (! in_array($batch->status, [PayoutBatch::STATUS_APPROVED, PayoutBatch::STATUS_PROCESSING], true)) {
                throw new FinanceException(trans('finance::payout_batch.messages.not_approved'), 'not_approved');
            }

            ProcessPayoutBatch::dispatch($batch->id);
        }, 'processing');
    }

    public function exportBankFile(Request $request): StreamedResponse|RedirectResponse
    {
        $batch = $this->findBatch($request);

        if ($batch === null || $batch->isDraft()) {
            return redirect()->back()->with('error', trans('finance::payout_batch.messages.not_approved'));
        }

        $rows = $this->payouts->bankFileRows($batch);

        return CsvExport::stream($batch->batch_no.'-bank-transfer.csv', ['payout_no', 'beneficiary_name', 'account_number', 'ifsc', 'amount', 'narration', 'seller_code'], fn () => array_map('array_values', $rows));
    }

    public function uploadUtr(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['nullable', 'required_without:utrs', 'file', 'mimes:csv,txt', 'max:2048'],
            'utrs' => ['nullable', 'array'],
            'utrs.*' => ['nullable', 'string', 'max:40'],
        ]);

        $batch = $this->findBatch($request);

        if ($batch === null) {
            return redirect()->back()->with('error', trans('finance::finance.messages.not_found'));
        }

        $utrs = array_filter((array) $request->input('utrs', []), fn ($utr): bool => is_string($utr) && trim($utr) !== '');

        if ($request->hasFile('file')) {
            $handle = fopen($request->file('file')->getRealPath(), 'rb');

            while ($handle !== false && ($row = fgetcsv($handle, escape: '\\')) !== false) {
                $payoutNo = strtoupper(trim((string) ($row[0] ?? '')));

                if (str_starts_with($payoutNo, 'PAY-') && trim((string) ($row[1] ?? '')) !== '') {
                    $utrs[$payoutNo] = trim((string) $row[1]);
                }
            }

            if ($handle !== false) {
                fclose($handle);
            }
        }

        $result = $this->payouts->uploadUtrs($batch, $utrs, $request->user());
        $redirect = redirect()->route('admin.payout_batch.show', updateUrlParams([$batch->id]))
            ->with('success', trans('finance::payout_batch.messages.utrs_applied', ['count' => $result['paid']]));

        return $result['errors'] === [] ? $redirect : $redirect->with('error', implode(' ', array_slice($result['errors'], 0, 10)));
    }

    private function act(Request $request, \Closure $action, string $message): RedirectResponse
    {
        $batch = $this->findBatch($request);

        if ($batch === null) {
            return redirect()->back()->with('error', trans('finance::finance.messages.not_found'));
        }

        try {
            $action($batch);
        } catch (FinanceException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.payout_batch.show', updateUrlParams([$batch->id]))->with('success', trans('finance::payout_batch.messages.'.$message, ['batch' => $batch->batch_no]));
    }

    private function findBatch(Request $request): ?PayoutBatch
    {
        $batch = $this->gridRepository()->find((int) $request->route('id'));

        return $batch instanceof PayoutBatch ? $batch : null;
    }
}

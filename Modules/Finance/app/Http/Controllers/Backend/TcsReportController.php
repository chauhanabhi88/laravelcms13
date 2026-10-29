<?php

declare(strict_types=1);

namespace Modules\Finance\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Finance\Services\TcsTdsCalculator;
use Modules\Finance\Support\CsvExport;
use Modules\Finance\Support\FinanceSettings;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * TCS report (GSTR-8 support, plan M17 §5.6): per seller GSTIN for a month, gross supplies,
 * returns, net taxable value and the TCS collected (CGST / SGST / IGST), with CSV export.
 */
class TcsReportController extends BackendController
{
    public function __construct(private readonly TcsTdsCalculator $calculator)
    {
        parent::__construct();
    }

    public function index(Request $request): View
    {
        $period = $this->period($request);

        return view('finance::backend.tcs_report.index', [
            'period' => $period,
            'rows' => $this->calculator->monthlyReport($period),
            'rate' => FinanceSettings::tcsRate(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $period = $this->period($request);
        $rows = $this->calculator->monthlyReport($period);

        return CsvExport::stream('tcs-report-'.$period.'.csv', [
            'period', 'seller_code', 'seller', 'gstin', 'gross_taxable', 'returns_taxable', 'net_taxable', 'tcs_cgst', 'tcs_sgst', 'tcs_igst', 'tcs_total', 'tcs_at_rate',
        ], fn () => array_map(fn (array $row): array => [
            $period,
            $row['code'],
            $row['seller'],
            $row['gstin'],
            $row['gross']->toDecimal(),
            $row['returns']->toDecimal(),
            $row['net']->toDecimal(),
            $row['cgst']->toDecimal(),
            $row['sgst']->toDecimal(),
            $row['igst']->toDecimal(),
            $row['tcs']->toDecimal(),
            $row['computed']->toDecimal(),
        ], $rows));
    }

    private function period(Request $request): string
    {
        $period = (string) $request->query('period', '');

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period) ? $period : Carbon::now()->subMonthNoOverflow()->format('Y-m');
    }
}

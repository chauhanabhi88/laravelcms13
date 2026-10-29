<?php

declare(strict_types=1);

namespace Modules\Report\Http\Controllers\Backend;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Report\Models\ReportExport;
use Modules\Report\Services\ReportExporter;
use Modules\Report\Services\ReportTables;
use Symfony\Component\HttpFoundation\Response;

/**
 * Report exports (FR-RPT-005): start an export of the report being viewed (streamed, or queued
 * above report.export_sync_limit rows), list the admin's queued exports and download them.
 * Exporting also needs the permission of the exported report.
 */
class ReportExportController extends BackendController
{
    public function __construct(private readonly ReportExporter $exporter)
    {
        parent::__construct();
    }

    public function index(Request $request): View
    {
        return view('report::backend.exports', [
            'exports' => ReportExport::query()->where('user_id', $request->user()->id)->latest('id')->limit(50)->get(),
        ]);
    }

    public function start(Request $request): Response|RedirectResponse
    {
        $report = (string) $request->query('report', '');
        $format = (string) $request->query('format', 'csv');

        abort_unless(in_array($report, ReportTables::REPORTS, true), 404);
        abort_unless(Gate::allows('admin.report.'.$report), 403);

        if (! in_array($format, (array) config('report.exports.formats', ['csv']), true)) {
            $format = 'csv';
        }

        $query = array_intersect_key($request->query(), array_flip(['preset', 'from', 'to', 'compare', 'seller_id', 'category_id', 'state_id', 'payment', 'sort']));
        $result = $this->exporter->export($request->user(), $report, $format, $query);

        if ($result instanceof ReportExport) {
            return redirect()->route('admin.report.export', updateUrlParams())
                ->with('success', trans('report::report.exports.queued'));
        }

        return $result;
    }

    public function download(Request $request): Response
    {
        $export = ReportExport::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail((int) $request->route('id'));

        abort_unless($export->isReady() && $this->exporter->disk()->exists((string) $export->path), 404);

        return $this->exporter->disk()->download((string) $export->path, basename((string) $export->path));
    }
}

<?php

declare(strict_types=1);

namespace Modules\Report\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Core\Exports\CoreExport;
use Modules\Finance\Support\CsvExport;
use Modules\Report\Jobs\ExportReport;
use Modules\Report\Models\ReportExport;
use Modules\Report\Notifications\ReportExportReady;
use Modules\Report\Support\Format;
use Modules\Report\Support\ReportFilters;
use Modules\Report\Support\ReportRange;
use Modules\User\Models\User;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Report exports (plan M19 §5 "Exports"): up to report.export_sync_limit rows stream straight
 * away as CSV or Excel; larger exports are written by the queued ExportReport job to the
 * private disk and downloaded from Reports > Exports.
 */
class ReportExporter
{
    public function __construct(private readonly ReportTables $tables) {}

    /**
     * @param  array<string, mixed>  $query  range + filter + sort parameters, as the page sends them
     * @return Response|ReportExport the download, or the queued export
     */
    public function export(User $user, string $report, string $format, array $query): Response|ReportExport
    {
        [$range, $filters] = self::context($query);

        if ($this->tables->count($report, $range, $filters) <= (int) config('report.export_sync_limit', 5000)) {
            return $this->download($report, $format, $range, $filters, $query);
        }

        $export = ReportExport::query()->create([
            'user_id' => $user->id,
            'report' => $report,
            'format' => $format,
            'filters' => $query,
            'status' => ReportExport::STATUS_QUEUED,
        ]);

        ExportReport::dispatch($export->id);

        return $export;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function download(string $report, string $format, ReportRange $range, ReportFilters $filters, array $query): Response
    {
        [$header, $rows] = $this->rows($report, $range, $filters, $query);
        $name = $this->filename($report, $range, $format);

        if ($format === 'xlsx') {
            return Excel::download(new CoreExport($rows, $header), $name);
        }

        return CsvExport::stream($name, $header, fn (): array => $rows);
    }

    /**
     * Write a queued export to the disk (ExportReport). Idempotent: a finished export is left
     * as it is.
     */
    public function write(ReportExport $export): void
    {
        if ($export->status === ReportExport::STATUS_READY) {
            return;
        }

        $export->forceFill(['status' => ReportExport::STATUS_PROCESSING])->save();

        try {
            [$range, $filters] = self::context($export->filters);
            [$header, $rows] = $this->rows($export->report, $range, $filters, $export->filters);
            $path = trim((string) config('report.exports.folder', 'report-exports'), '/').'/'.$export->id.'-'.$this->filename($export->report, $range, $export->format);

            if ($export->format === 'xlsx') {
                Excel::store(new CoreExport($rows, $header), $path, (string) config('report.exports.disk', 'private'));
            } else {
                $this->disk()->put($path, $this->csv($header, $rows));
            }

            $export->forceFill([
                'status' => ReportExport::STATUS_READY,
                'rows' => count($rows),
                'path' => $path,
                'error' => null,
                'completed_at' => Carbon::now(),
            ])->save();
        } catch (Throwable $e) {
            $export->forceFill(['status' => ReportExport::STATUS_FAILED, 'error' => mb_substr($e->getMessage(), 0, 500)])->save();

            throw $e;
        }

        try {
            User::query()->find($export->user_id)?->notify(new ReportExportReady($export));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Delete exports (rows and files) older than report.exports.keep_days.
     */
    public function prune(): int
    {
        $expired = ReportExport::query()
            ->where('created_at', '<', Carbon::now()->subDays(max(1, (int) config('report.exports.keep_days', 7))))
            ->get();

        foreach ($expired as $export) {
            if ($export->path !== null) {
                $this->disk()->delete($export->path);
            }

            $export->delete();
        }

        return $expired->count();
    }

    public function disk(): Filesystem
    {
        return Storage::disk((string) config('report.exports.disk', 'private'));
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{0: ReportRange, 1: ReportFilters}
     */
    public static function context(array $query): array
    {
        return [ReportRange::fromInput($query), ReportFilters::fromInput($query)];
    }

    public function filename(string $report, ReportRange $range, string $format): string
    {
        return 'report-'.$report.'-'.$range->from->format('Ymd').'-'.$range->to->format('Ymd').'.'.($format === 'xlsx' ? 'xlsx' : 'csv');
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{0: list<string>, 1: list<list<string|int|float>>}
     */
    private function rows(string $report, ReportRange $range, ReportFilters $filters, array $query): array
    {
        $table = $this->tables->table($report, $range, $filters, null, ['sort' => $query['sort'] ?? null]);

        return [
            array_column($table['columns'], 'label'),
            array_map(fn (array $row): array => array_map(
                fn (array $column) => Format::export($row[$column['key']] ?? null, $column['format']),
                $table['columns'],
            ), $table['rows']),
        ];
    }

    /**
     * @param  list<string>  $header
     * @param  list<list<string|int|float>>  $rows
     */
    private function csv(array $header, array $rows): string
    {
        $out = fopen('php://temp', 'w+b');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $header, escape: '\\');

        foreach ($rows as $row) {
            fputcsv($out, array_map(CsvExport::cell(...), $row), escape: '\\');
        }

        rewind($out);
        $content = (string) stream_get_contents($out);
        fclose($out);

        return $content;
    }
}

<?php

declare(strict_types=1);

namespace Modules\Product\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Product\Exports\UploadErrorReportExport;
use Modules\Product\Jobs\ProcessCatalogUpload;
use Modules\Product\Jobs\ProcessPriceStockUpload;
use Modules\Product\Models\CatalogUpload;
use Modules\Product\Models\CatalogUploadRowError;
use Modules\Product\Notifications\CatalogUploadFinished;
use Modules\Seller\Models\Seller;
use Modules\Seller\Models\SellerUser;

/**
 * Bulk upload bookkeeping (FR-SCT-003/005/009): stores the file on the private disk, creates
 * the catalog_upload row, queues the processing job, and records the outcome (counters,
 * row errors, the error report, the seller notification).
 *
 * @phpstan-type RowProblem array{column: string|null, message: string, level?: string}
 */
class CatalogUploadService
{
    public function createNewCatalog(Seller $seller, SellerUser $user, int $categoryId, UploadedFile $file, bool $saveAsDraft): CatalogUpload
    {
        $upload = $this->create($seller, $user, CatalogUpload::TYPE_NEW_CATALOG, $file, [
            'catalog_category_id' => $categoryId,
            'save_as_draft' => $saveAsDraft ? config('core.yes') : config('core.no'),
        ]);

        ProcessCatalogUpload::dispatch($upload->id);

        return $upload->refresh();
    }

    public function createPriceStock(Seller $seller, SellerUser $user, UploadedFile $file): CatalogUpload
    {
        $upload = $this->create($seller, $user, CatalogUpload::TYPE_PRICE_STOCK, $file);

        ProcessPriceStockUpload::dispatch($upload->id);

        return $upload->refresh();
    }

    /**
     * Mark the upload as being processed; false when it was already picked up.
     */
    public function start(CatalogUpload $upload): bool
    {
        $claimed = CatalogUpload::query()
            ->whereKey($upload->id)
            ->where('status', CatalogUpload::STATUS_QUEUED)
            ->update(['status' => CatalogUpload::STATUS_PROCESSING, 'started_at' => now(), 'updated_at' => now()]);

        $upload->refresh();

        return $claimed === 1;
    }

    /**
     * Record the result of a processed upload.
     *
     * @param  array<int, list<RowProblem>>  $problems  row number => problems (errors and warnings)
     * @param  list<string>  $headings  the uploaded heading row
     * @param  array<int, list<string>>  $failedRows  row number => uploaded cells of the rows not saved
     */
    public function finish(CatalogUpload $upload, int $total, int $success, int $failed, array $problems, array $headings, array $failedRows): CatalogUpload
    {
        $this->storeProblems($upload, $problems);

        $reportPath = null;
        if ($failedRows !== []) {
            $messages = [];
            foreach ($problems as $row => $rowProblems) {
                foreach ($rowProblems as $problem) {
                    if (($problem['level'] ?? CatalogUploadRowError::LEVEL_ERROR) === CatalogUploadRowError::LEVEL_ERROR) {
                        $messages[$row][] = $problem['message'];
                    }
                }
            }

            ksort($failedRows);
            $reportPath = $this->folder($upload->seller_id).'/errors-'.$upload->id.'-'.Str::lower(Str::random(8)).'.xlsx';
            Excel::store(new UploadErrorReportExport($headings, $failedRows, $messages), $reportPath, $this->disk());
        }

        $upload->forceFill([
            'status' => $failed === 0 ? CatalogUpload::STATUS_COMPLETED : ($success === 0 ? CatalogUpload::STATUS_FAILED : CatalogUpload::STATUS_COMPLETED_WITH_ERRORS),
            'total_rows' => $total,
            'success_rows' => $success,
            'failed_rows' => $failed,
            'error_report_path' => $reportPath,
            'finished_at' => now(),
        ])->save();

        $this->notify($upload);

        return $upload;
    }

    /**
     * The whole file was refused (unreadable, wrong template, too many rows…).
     */
    public function fail(CatalogUpload $upload, string $message, int $total = 0): CatalogUpload
    {
        $this->storeProblems($upload, [0 => [['column' => null, 'message' => $message]]]);

        $upload->forceFill([
            'status' => CatalogUpload::STATUS_FAILED,
            'total_rows' => $total,
            'success_rows' => 0,
            'failed_rows' => $total,
            'finished_at' => now(),
        ])->save();

        $this->notify($upload);

        return $upload;
    }

    public function disk(): string
    {
        return (string) config('product.bulk_disk', 'private');
    }

    public function folder(int $sellerId): string
    {
        return trim((string) config('product.bulk_folder', 'catalog_uploads'), '/').'/'.$sellerId;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function create(Seller $seller, SellerUser $user, string $type, UploadedFile $file, array $attributes = []): CatalogUpload
    {
        $extension = strtolower((string) $file->getClientOriginalExtension()) === 'csv' || strtolower((string) $file->getClientOriginalExtension()) === 'txt' ? 'csv' : 'xlsx';
        $path = $file->storeAs($this->folder($seller->id), Str::random(40).'.'.$extension, $this->disk());

        return CatalogUpload::query()->create($attributes + [
            'seller_id' => $seller->id,
            'seller_user_id' => $user->id,
            'type' => $type,
            'file_path' => (string) $path,
            'original_name' => Str::limit(basename((string) $file->getClientOriginalName()), 250, ''),
            'status' => CatalogUpload::STATUS_QUEUED,
        ]);
    }

    /**
     * @param  array<int, list<RowProblem>>  $problems
     */
    protected function storeProblems(CatalogUpload $upload, array $problems): void
    {
        $rows = [];
        $now = now();

        ksort($problems);
        foreach ($problems as $row => $rowProblems) {
            foreach ($rowProblems as $problem) {
                $rows[] = [
                    'catalog_upload_id' => $upload->id,
                    'row_number' => (int) $row,
                    'column' => $problem['column'] !== null ? Str::limit((string) $problem['column'], 60, '') : null,
                    'level' => $problem['level'] ?? CatalogUploadRowError::LEVEL_ERROR,
                    'message' => Str::limit((string) $problem['message'], 495),
                    'created_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            CatalogUploadRowError::query()->insert($chunk);
        }
    }

    protected function notify(CatalogUpload $upload): void
    {
        $user = $upload->seller_user_id !== null ? SellerUser::query()->find($upload->seller_user_id) : null;

        if ($user === null) {
            return;
        }

        try {
            $user->notify(new CatalogUploadFinished($upload));
        } catch (\Throwable $e) {
            Log::warning('Catalog upload notification failed', ['upload' => $upload->id, 'error' => $e->getMessage()]);
        }
    }
}

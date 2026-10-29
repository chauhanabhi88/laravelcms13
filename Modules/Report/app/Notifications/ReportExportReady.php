<?php

declare(strict_types=1);

namespace Modules\Report\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\Report\Models\ReportExport;

/**
 * Tells the admin that a queued report export can be downloaded (database channel; the
 * Reports > Exports screen lists it too).
 */
class ReportExportReady extends Notification
{
    use Queueable;

    public function __construct(public readonly ReportExport $export) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'export_id' => $this->export->id,
            'report' => $this->export->report,
            'rows' => $this->export->rows,
            'message' => trans('report::report.exports.ready_message', ['report' => trans('report::report.reports.'.$this->export->report)]),
            'url' => route('admin.report.export_download', updateUrlParams([$this->export->id])),
        ];
    }
}

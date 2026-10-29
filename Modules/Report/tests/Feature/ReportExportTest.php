<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Report\Models\ReportExport;
use Modules\Report\Notifications\ReportExportReady;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    reportSetup();
    Storage::fake('private');
});

test('a small export streams a CSV of the report table', function () {
    $day = CarbonImmutable::today()->subDays(2);
    statRow($day->toDateString(), ['orders' => 4, 'units' => 6, 'gmv' => '2400.50', 'discount' => '100.00']);
    $query = ['report' => 'sales', 'format' => 'csv', 'preset' => 'custom', 'from' => $day->toDateString(), 'to' => $day->addDay()->toDateString()];

    $response = $this->actingAs(reportAdmin())->get(route('admin.report.export_start', $query));
    $csv = $response->assertOk()->streamedContent();
    $lines = array_map('str_getcsv', array_filter(explode("\n", str_replace("\xEF\xBB\xBF", '', $csv))));

    expect($response->headers->get('content-disposition'))->toContain('report-sales-')
        ->and($lines[0][0])->toBe(trans('report::report.columns.date'))
        ->and($lines[0])->toContain(trans('report::report.columns.gmv'))
        ->and($lines)->toHaveCount(3)
        ->and($lines[2])->toContain($day->toDateString())
        ->and($lines[2])->toContain('2400.50')
        ->and($lines[2])->toContain('600.12');
});

test('Excel exports go through maatwebsite', function () {
    Excel::fake();

    $this->actingAs(reportAdmin())->get(route('admin.report.export_start', ['report' => 'sellers', 'format' => 'xlsx', 'preset' => 'last_7']))->assertOk();

    Excel::assertDownloaded('report-sellers-'.CarbonImmutable::today()->subDays(6)->format('Ymd').'-'.CarbonImmutable::today()->format('Ymd').'.xlsx');
});

test('a large export is queued, written to the private disk and downloaded only by its owner', function () {
    config(['report.export_sync_limit' => 1]);
    Notification::fake();
    $admin = reportAdmin();
    statRow(CarbonImmutable::today()->subDays(2)->toDateString(), ['orders' => 2, 'gmv' => '800.00']);

    $this->actingAs($admin)->get(route('admin.report.export_start', ['report' => 'payments', 'preset' => 'last_7']))
        ->assertRedirect(route('admin.report.export'))
        ->assertSessionHas('success', trans('report::report.exports.queued'));

    $export = ReportExport::query()->where('user_id', $admin->id)->sole();

    expect($export)->status->toBe(ReportExport::STATUS_READY)->rows->toBe(7)->format->toBe('csv')
        ->and(Storage::disk('private')->exists($export->path))->toBeTrue()
        ->and(Storage::disk('private')->get($export->path))->toContain(trans('report::report.columns.cod_share'));

    Notification::assertSentTo($admin, ReportExportReady::class);

    $this->actingAs($admin)->get(route('admin.report.export'))->assertOk()->assertSee('data-report-export-row="'.$export->id.'"', false);
    $this->actingAs($admin)->get(route('admin.report.export_download', [$export->id]))->assertOk()->assertDownload(basename($export->path));
    $this->actingAs(reportAdmin())->get(route('admin.report.export_download', [$export->id]))->assertNotFound();
});

test('exports of unknown reports or without the report permission are refused', function () {
    $this->actingAs(reportAdmin())->get(route('admin.report.export_start', ['report' => 'everything']))->assertNotFound();
    $this->actingAs(reportAdmin(['admin.report.export']))->get(route('admin.report.export_start', ['report' => 'search']))->assertForbidden();
    $this->actingAs(reportAdmin(['admin.report.sales']))->get(route('admin.report.export_start', ['report' => 'sales']))->assertForbidden();
});

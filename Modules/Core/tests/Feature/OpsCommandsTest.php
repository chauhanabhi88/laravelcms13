<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Core\Support\Ops\LaunchChecklist;
use Modules\Core\Support\Ops\QueueMonitor;

/*
| Plan M21 S7 / §4: the go-live preflight and the queue health alerts.
*/

function opsFailedJob(string $queue, ?DateTimeInterface $failedAt = null): void
{
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => $queue,
        'payload' => '{}',
        'exception' => 'RuntimeException: boom',
        'failed_at' => $failedAt ?? now(),
    ]);
}

/**
 * @return array<string, string> check => status
 */
function opsChecks(): array
{
    return array_column(app(LaunchChecklist::class)->run(), 'status', 'check');
}

test('the launch check blocks debug mode, fake drivers and an insecure site', function () {
    config([
        'app.debug' => true,
        'app.url' => 'http://shop.test',
        'core.sms.driver' => 'log',
        'payment.default_gateway' => 'fake',
    ]);

    $checks = opsChecks();

    expect($checks['APP_DEBUG is off'])->toBe(LaunchChecklist::FAIL)
        ->and($checks['APP_URL uses https'])->toBe(LaunchChecklist::FAIL)
        ->and($checks['SMS gateway is live'])->toBe(LaunchChecklist::FAIL)
        ->and($checks['Prepaid gateway is Razorpay'])->toBe(LaunchChecklist::FAIL)
        ->and(LaunchChecklist::passes(app(LaunchChecklist::class)->run()))->toBeFalse();

    $this->artisan('core:launch-check')->assertFailed();
});

test('production settings pass the blocking checks', function () {
    config([
        'app.debug' => false,
        'app.url' => 'https://shop.example',
        'session.secure' => true,
        'core.sms.driver' => 'msg91',
        'payment.default_gateway' => 'razorpay',
        'payment.razorpay.key_id' => 'rzp_live_abc',
        'payment.razorpay.webhook_secret' => 'secret',
        'storefront.seo.robots_block_all' => false,
        'core.security.csp.mode' => 'enforce',
    ]);

    $checks = opsChecks();

    expect($checks['APP_DEBUG is off'])->toBe(LaunchChecklist::PASS)
        ->and($checks['APP_URL uses https'])->toBe(LaunchChecklist::PASS)
        ->and($checks['Session cookie is secure'])->toBe(LaunchChecklist::PASS)
        ->and($checks['Razorpay live keys'])->toBe(LaunchChecklist::PASS)
        ->and($checks['Crawlers are allowed'])->toBe(LaunchChecklist::PASS)
        ->and($checks['CSP is enforced'])->toBe(LaunchChecklist::PASS);
});

test('a failed webhook job is a critical alert and other failures are warnings', function () {
    opsFailedJob('webhooks');
    opsFailedJob('notifications');
    opsFailedJob('notifications', now()->subDays(3));

    $alerts = collect(app(QueueMonitor::class)->check())->keyBy('key');

    expect($alerts['failed:webhooks']['level'])->toBe('critical')
        ->and($alerts['failed:notifications']['level'])->toBe('warning')
        ->and($alerts['failed:notifications']['message'])->toStartWith('1 failed job');
});

test('a queue backlog over the threshold is critical', function () {
    config(['core.monitor.backlog_threshold' => 1, 'core.monitor.queues' => ['m21-test'], 'queue.default' => 'database']);

    foreach ([1, 2] as $attempt) {
        DB::table('jobs')->insert(['queue' => 'm21-test', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->getTimestamp(), 'created_at' => now()->getTimestamp()]);
    }

    $alerts = collect(app(QueueMonitor::class)->check())->keyBy('key');

    expect($alerts)->toHaveKey('backlog:m21-test')
        ->and($alerts['backlog:m21-test']['level'])->toBe('critical');
});

test('an alert is logged once per repeat window', function () {
    $logger = Mockery::mock();
    $logger->shouldReceive('critical')->once();
    Log::shouldReceive('channel')->with('stack')->once()->andReturn($logger);

    $alerts = [['key' => 'failed:webhooks', 'level' => 'critical', 'message' => '1 failed job(s) on queue [webhooks].']];
    $monitor = app(QueueMonitor::class);

    expect($monitor->notify($alerts))->toBe(1)
        ->and($monitor->notify($alerts))->toBe(0);
});

test('the monitor command reports healthy queues', function () {
    config(['core.monitor.queues' => []]);
    DB::table('failed_jobs')->delete();

    $this->artisan('core:monitor')->expectsOutput('All queues healthy.')->assertSuccessful();
});

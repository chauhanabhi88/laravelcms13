<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

/**
 * Receives Content-Security-Policy violation reports (application/csp-report) and logs them to
 * the "security" channel, so the report-only policy can be tuned before it is enforced.
 */
class CspReportController extends Controller
{
    /**
     * Reports are capped in size; anything that is not a CSP report is ignored.
     */
    protected const MAX_BYTES = 8192;

    public function store(Request $request): Response
    {
        $body = substr((string) $request->getContent(), 0, self::MAX_BYTES);
        $payload = json_decode($body, true);
        $report = is_array($payload) ? ($payload['csp-report'] ?? null) : null;

        if (is_array($report)) {
            Log::channel('security')->warning('CSP violation', [
                'document' => $this->clean($report['document-uri'] ?? null),
                'directive' => $this->clean($report['effective-directive'] ?? $report['violated-directive'] ?? null),
                'blocked' => $this->clean($report['blocked-uri'] ?? null),
                'source' => $this->clean($report['source-file'] ?? null),
                'line' => is_numeric($report['line-number'] ?? null) ? (int) $report['line-number'] : null,
                'sample' => $this->clean($report['script-sample'] ?? null),
                'ip' => $request->ip(),
            ]);
        }

        return response()->noContent();
    }

    protected function clean(mixed $value): ?string
    {
        return is_string($value) ? mb_substr($value, 0, 500) : null;
    }
}

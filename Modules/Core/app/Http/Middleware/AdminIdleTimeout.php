<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs an admin ("web" guard) out after config('core.security.admin_idle_minutes') without a
 * request (plan M21 S10). Appended to the "web" group, so it runs after the session starts.
 */
class AdminIdleTimeout
{
    public const SESSION_KEY = 'admin_last_activity_at';

    public function handle(Request $request, Closure $next): Response
    {
        $minutes = (int) config('core.security.admin_idle_minutes', 30);

        if ($minutes <= 0 || ! $request->hasSession() || ! Auth::guard('web')->check()) {
            return $next($request);
        }

        $session = $request->session();
        $lastActivity = (int) $session->get(self::SESSION_KEY, 0);

        if ($lastActivity > 0 && now()->getTimestamp() - $lastActivity > $minutes * 60) {
            return $this->logout($request, $minutes);
        }

        $session->put(self::SESSION_KEY, now()->getTimestamp());

        return $next($request);
    }

    protected function logout(Request $request, int $minutes): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $message = trans('user::user.messages.session_timed_out', ['minutes' => $minutes]);

        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message, 'code' => 401], 401);
        }

        return redirect(route('backend_login', updateUrlParams()))->withErrors(['email' => $message]);
    }
}

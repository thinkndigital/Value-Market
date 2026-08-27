<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 2 (docs/PHASE_2_IDOR_AUDIT.md §4, "CronJobController..." row): settleCashbackDiscount() and
 * sendCartReminders() were reachable with no authentication or permission check at all - any visitor
 * could trigger a wallet-cashback settlement run or burn the site's paid Gemini/OpenRouter API quota by
 * hitting the URL repeatedly. Both are meant to be triggered by an external system cron (no user session
 * exists for that request), so gating them behind `auth`/`permissions` like a normal admin route would
 * break that automation - a shared secret is the correct fit instead of a login.
 */
class VerifyCronSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = config('constants.CRON_SECRET');

        if (empty($configured) || !hash_equals((string) $configured, (string) $request->query('cron_secret', ''))) {
            abort(403);
        }

        return $next($request);
    }
}

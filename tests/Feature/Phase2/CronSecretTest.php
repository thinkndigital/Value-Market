<?php

namespace Tests\Feature\Phase2;

use App\Http\Middleware\VerifyCronSecret;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Phase 2 (docs/PHASE_2_IDOR_AUDIT.md §4): settleCashbackDiscount() and sendCartReminders() were reachable
 * with no authentication or permission check at all - fixed with a shared-secret middleware instead of a
 * login gate, since both are meant to be hit by an external cron with no user session. Proves: no secret
 * configured fails closed (403), a missing/wrong query param is rejected, and the correct secret passes
 * through to the next handler.
 */
class CronSecretTest extends TestCase
{
    private function callMiddleware(Request $request): mixed
    {
        $middleware = new VerifyCronSecret();
        $called = false;
        $result = $middleware->handle($request, function ($req) use (&$called) {
            $called = true;
            return new \Symfony\Component\HttpFoundation\Response('next-called');
        });

        return $called ? $result->getContent() : null;
    }

    public function test_unconfigured_secret_fails_closed(): void
    {
        config(['constants.CRON_SECRET' => null]);
        $request = Request::create('/admin/cronjob/sendCartReminders', 'GET', ['cron_secret' => 'anything']);

        try {
            $this->callMiddleware($request);
            $this->fail('Expected a 403 when CRON_SECRET is not configured.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_missing_query_param_is_rejected(): void
    {
        config(['constants.CRON_SECRET' => 'the-real-secret']);
        $request = Request::create('/admin/cronjob/sendCartReminders', 'GET');

        try {
            $this->callMiddleware($request);
            $this->fail('Expected a 403 with no cron_secret query param.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_wrong_secret_is_rejected(): void
    {
        config(['constants.CRON_SECRET' => 'the-real-secret']);
        $request = Request::create('/admin/cronjob/sendCartReminders', 'GET', ['cron_secret' => 'guessed-wrong']);

        try {
            $this->callMiddleware($request);
            $this->fail('Expected a 403 for a wrong cron_secret.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_correct_secret_passes_through(): void
    {
        config(['constants.CRON_SECRET' => 'the-real-secret']);
        $request = Request::create('/admin/cronjob/sendCartReminders', 'GET', ['cron_secret' => 'the-real-secret']);

        $this->assertSame('next-called', $this->callMiddleware($request));
    }
}

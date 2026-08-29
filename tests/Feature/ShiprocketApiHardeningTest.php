<?php

namespace Tests\Feature;

use App\Libraries\Shiprocket;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * docs/CHANGELOG_FEATURE_AUDIT.md (P1, "Shiprocket depth audit"): app/Libraries/Shiprocket.php called
 * Shiprocket's /auth/login on every single API call (deliverability checks alone can fire several times per
 * cart/checkout page load - see DeliveryService::checkCartProductsDeliverable()), had no HTTP timeout at all
 * (auth: CURLOPT_TIMEOUT => 0 = unlimited; every other call: no timeout option set = also unlimited), and
 * never inspected the HTTP status of a failed call - a curl-level failure (or any non-2xx) came back as
 * whatever json_decode(false) produced, which downstream callers happened to tolerate via isset() checks
 * but which was never a deliberate, safe "Shiprocket is unavailable" contract.
 *
 * Real Shiprocket cannot be reached from this sandbox, so these tests run app/Libraries/Shiprocket.php
 * against a tiny fake server (tests/Fixtures/shiprocket_fake_server.php) via config('services.shiprocket.*')
 * pointing at 127.0.0.1 instead of the real API - proving the actual HTTP behavior of the hardened client,
 * not just its PHP-level branching.
 */
class ShiprocketApiHardeningTest extends TestCase
{
    use RefreshDatabase;

    private $serverProcess;
    private string $logFile;
    private int $port;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::forceCreate(['variable' => 'shipping_method', 'value' => json_encode([
            'shiprocket_shipping_method' => 1,
            'email' => 'ops@example.test',
            'password' => 'not-a-real-password',
        ])]);

        $this->logFile = tempnam(sys_get_temp_dir(), 'sr_log_');
        file_put_contents($this->logFile, '');
        $this->port = $this->findFreePort();

        $php = PHP_BINARY;
        $router = __DIR__ . '/../Fixtures/shiprocket_fake_server.php';
        $env = array_merge($_ENV, [
            'SHIPROCKET_TEST_LOG' => $this->logFile,
            'SHIPROCKET_TEST_VALID_TOKEN' => 'fresh-token-issued-by-fake-server',
        ]);

        $this->serverProcess = proc_open(
            [$php, '-S', "127.0.0.1:{$this->port}", $router],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            $env
        );

        $this->waitForServer();

        config([
            'services.shiprocket.base_url' => "http://127.0.0.1:{$this->port}/",
            'services.shiprocket.timeout' => 15,
            'services.shiprocket.connect_timeout' => 5,
            'services.shiprocket.token_ttl_minutes' => 12960,
        ]);
    }

    protected function tearDown(): void
    {
        if (is_resource($this->serverProcess)) {
            proc_terminate($this->serverProcess);
            proc_close($this->serverProcess);
        }
        if (isset($this->logFile) && file_exists($this->logFile)) {
            @unlink($this->logFile);
        }
        parent::tearDown();
    }

    private function findFreePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $name = stream_socket_get_name($socket, false);
        fclose($socket);
        return (int) substr($name, strrpos($name, ':') + 1);
    }

    private function waitForServer(): void
    {
        $deadline = microtime(true) + 5;
        while (microtime(true) < $deadline) {
            $conn = @fsockopen('127.0.0.1', $this->port, $errno, $errstr, 0.2);
            if ($conn) {
                fclose($conn);
                return;
            }
            usleep(50000);
        }
        $this->fail('Fake Shiprocket server did not start in time.');
    }

    private function requestLines(): array
    {
        return array_values(array_filter(explode("\n", file_get_contents($this->logFile))));
    }

    public function test_token_is_cached_across_multiple_calls_instead_of_reauthenticating_every_time(): void
    {
        $shiprocket = new Shiprocket();

        $first = $shiprocket->check_serviceability(['pickup_postcode' => '110001', 'delivery_postcode' => '400001', 'weight' => 1, 'cod' => 0]);
        $second = $shiprocket->check_serviceability(['pickup_postcode' => '110001', 'delivery_postcode' => '400001', 'weight' => 1, 'cod' => 0]);
        $third = $shiprocket->check_serviceability(['pickup_postcode' => '110001', 'delivery_postcode' => '400001', 'weight' => 1, 'cod' => 0]);

        $this->assertSame(200, $first['status'] ?? null);
        $this->assertSame(200, $second['status'] ?? null);
        $this->assertSame(200, $third['status'] ?? null);

        $authCalls = array_filter($this->requestLines(), fn($line) => str_starts_with($line, 'POST /auth/login'));
        $this->assertCount(
            1,
            $authCalls,
            'Three API calls must authenticate once and reuse the cached token, not call /auth/login every time.'
        );
    }

    public function test_a_stale_cached_token_is_refreshed_once_and_the_call_still_succeeds(): void
    {
        // Simulates the real-world case this fix targets: a token cached days ago has since expired
        // server-side. Seed the cache with a token the fake server will reject, matching the cache key
        // app/Libraries/Shiprocket.php actually uses (md5 of the configured email).
        Cache::put('shiprocket_auth_token_' . md5('ops@example.test'), 'a-stale-expired-token', now()->addDay());

        $shiprocket = new Shiprocket();
        $result = $shiprocket->check_serviceability(['pickup_postcode' => '110001', 'delivery_postcode' => '400001', 'weight' => 1, 'cod' => 0]);

        $this->assertSame(200, $result['status'] ?? null, 'A 401 from a stale token must trigger exactly one re-auth-and-retry, ending in success.');

        $lines = $this->requestLines();
        $unauthorizedHits = array_filter($lines, fn($line) => str_contains($line, 'auth=Bearer a-stale-expired-token'));
        $authCalls = array_filter($lines, fn($line) => str_starts_with($line, 'POST /auth/login'));

        $this->assertCount(1, $unauthorizedHits, 'The stale token must actually be tried once (and rejected) before refreshing.');
        $this->assertCount(1, $authCalls, 'Exactly one re-authentication call, not a retry loop.');
    }

    public function test_a_slow_shiprocket_response_is_cut_off_by_the_configured_timeout_not_left_to_hang(): void
    {
        config(['services.shiprocket.timeout' => 1, 'services.shiprocket.connect_timeout' => 1]);

        $shiprocket = new Shiprocket();

        $start = microtime(true);
        // weight=slow makes the fake server sleep 3s - well past the 1s timeout just configured.
        $result = $shiprocket->check_serviceability(['pickup_postcode' => '110001', 'delivery_postcode' => '400001', 'weight' => 'slow', 'cod' => 0]);
        $elapsed = microtime(true) - $start;

        $this->assertLessThan(
            2.5,
            $elapsed,
            'A slow/unresponsive Shiprocket must be cut off by the configured timeout, not hang for the full response time (or forever, as CURLOPT_TIMEOUT => 0 used to allow).'
        );
        $this->assertTrue($result['error'] ?? false, 'A timed-out call must come back as a safe, well-formed error array, never an uncaught exception.');
    }
}

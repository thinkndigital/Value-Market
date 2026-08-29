<?php

/**
 * Minimal fake Shiprocket API, run under PHP's built-in server (`php -S 127.0.0.1:PORT`) by
 * tests/Feature/ShiprocketApiHardeningTest.php. Real Shiprocket cannot be hit in this sandbox, so this
 * fixture stands in for it to prove three things the raw-curl app/Libraries/Shiprocket.php client cannot
 * prove any other way: (1) the bearer token is cached across calls instead of re-authenticating every time,
 * (2) a 401 triggers exactly one re-auth-and-retry rather than looping or giving up, (3) a slow response is
 * cut off by the configured timeout instead of hanging.
 */

$logFile = getenv('SHIPROCKET_TEST_LOG');
$validToken = getenv('SHIPROCKET_TEST_VALID_TOKEN') ?: 'fresh-token-issued-by-fake-server';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$authHeader = '';
foreach (getallheaders() as $name => $value) {
    if (strtolower($name) === 'authorization') {
        $authHeader = $value;
    }
}

if ($logFile) {
    file_put_contents($logFile, "{$_SERVER['REQUEST_METHOD']} {$path} auth={$authHeader}\n", FILE_APPEND);
}

header('Content-Type: application/json');

if ($path === '/auth/login') {
    echo json_encode(['token' => $validToken]);
    exit;
}

if ($path === '/courier/serviceability/') {
    $weight = $_GET['weight'] ?? '';

    if ($weight === 'slow') {
        // Only used by the timeout test - sleeps well past the short timeout that test configures, to prove
        // the client aborts instead of waiting the full duration.
        sleep(3);
        echo json_encode(['status' => 200, 'data' => []]);
        exit;
    }

    if ($authHeader !== 'Bearer ' . $validToken) {
        http_response_code(401);
        echo json_encode(['message' => 'Unauthorized: token expired or invalid']);
        exit;
    }

    echo json_encode([
        'status' => 200,
        'data' => [
            'recommended_courier_company_id' => 1,
            'available_courier_companies' => [
                ['courier_company_id' => 1, 'rate' => 55, 'etd' => '2026-09-05', 'estimated_delivery_days' => 3, 'courier_name' => 'Fake Courier'],
            ],
        ],
    ]);
    exit;
}

http_response_code(404);
echo json_encode(['error' => true, 'message' => 'not found in fake server: ' . $path]);

<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$body = file_get_contents('php://input') ?: '';
$input = $body === '' ? [] : json_decode($body, true);

$invoice = [
    'id' => 'inv_test_123',
    'mode' => 'test',
    'amount' => '149.0000',
    'currency' => 'USD',
    'reference_id' => 'order_123',
    'description' => 'Test order',
    'return_url' => 'https://merchant.test/thanks',
    'deposit_address' => null,
    'status' => 'unpaid',
    'amount_due' => '149.000000000000000000',
    'amount_overpaid' => '0.000000000000000000',
    'monitoring_ends_at' => null,
    'monitoring_status' => null,
    'direct_onchain_rails' => [],
];

$publicInvoice = $invoice;
unset($publicInvoice['reference_id']);
$publicInvoice['project'] = [
    'id' => 'proj_test_123',
    'name' => 'Test project',
    'logo_url' => null,
];
$publicInvoice['amount_paid'] = '0.000000000000000000';
$publicInvoice['payment_status'] = 'unpaid';
$publicInvoice['transfers'] = [];

header('Content-Type: application/json');

if (($_SERVER['HTTP_AUTHORIZATION'] ?? '') !== 'Bearer sk_test_123') {
    http_response_code(401);
    echo json_encode(['code' => 'invalid_secret_key', 'message' => 'Invalid secret key.']);
    return;
}

if ($path === '/v1/redirect-target') {
    failContract('unexpected_redirect_follow', [
        'method' => $method,
        'body' => $input,
    ]);
}

if ($method === 'POST' && $path === '/v1/invoices') {
    assertHeader('HTTP_ACCEPT', 'application/json');
    assertHeaderStartsWith('HTTP_USER_AGENT', 'invoq-php/');
    assertHeader('CONTENT_TYPE', 'application/json');

    if (($input['amount'] ?? null) === '0.001') {
        assertBody([
            'amount' => '0.001',
            'currency' => 'USD',
        ], $input);

        http_response_code(400);
        echo json_encode([
            'code' => 'invalid_amount',
            'message' => 'Invalid amount.',
            'fields' => [[
                'location' => 'body',
                'field' => 'amount',
                'code' => 'amount_too_small',
                'message' => 'Amount must be greater than or equal to 0.01.',
            ]],
            'meta' => ['min_amount' => '0.01'],
        ]);
        return;
    }

    if (($input['amount'] ?? null) === '151') {
        assertBody([
            'amount' => '151',
            'currency' => 'USD',
            'return_url' => null,
        ], $input);

        http_response_code(201);
        echo json_encode(['data' => [
            ...$invoice,
            'amount' => '151.0000',
            'amount_due' => '151.000000000000000000',
            'return_url' => null,
            'description' => null,
            'reference_id' => null,
        ]]);
        return;
    }

    if (($input['amount'] ?? null) === '502') {
        assertBody([
            'amount' => '502',
            'currency' => 'USD',
        ], $input);

        header('Content-Type: text/html');
        http_response_code(502);
        echo '<html>bad gateway</html>';
        return;
    }

    if (($input['amount'] ?? null) === '302') {
        assertBody([
            'amount' => '302',
            'currency' => 'USD',
        ], $input);

        header('Location: /v1/redirect-target');
        http_response_code(302);
        echo json_encode(['code' => 'redirect', 'message' => 'Redirect.']);
        return;
    }

    if (($input['amount'] ?? null) === '150') {
        assertBody([
            'amount' => '150',
            'currency' => 'USD',
        ], $input);

        http_response_code(201);
        echo json_encode(['data' => [
            ...$invoice,
            'amount' => '150.0000',
            'amount_due' => '150.000000000000000000',
            'description' => null,
            'reference_id' => null,
            'return_url' => null,
        ]]);
        return;
    }

    assertBody([
        'amount' => '149',
        'currency' => 'USD',
        'description' => 'Test order',
        'reference_id' => 'order_123',
        'return_url' => 'https://merchant.test/thanks',
    ], $input);

    http_response_code(201);
    echo json_encode(['data' => $invoice]);
    return;
}

if ($method === 'GET' && $path === '/v1/invoices/inv_test_123') {
    assertHeader('HTTP_ACCEPT', 'application/json');
    assertHeaderStartsWith('HTTP_USER_AGENT', 'invoq-php/');
    assertNoHeader('CONTENT_TYPE');
    assertBody([], $input);

    echo json_encode(['data' => $publicInvoice]);
    return;
}

if ($method === 'POST' && $path === '/v1/invoices/inv_test_123/test-payments') {
    assertHeader('HTTP_ACCEPT', 'application/json');
    assertHeaderStartsWith('HTTP_USER_AGENT', 'invoq-php/');
    assertHeader('CONTENT_TYPE', 'application/json');

    if (($input['amount'] ?? null) === '150') {
        assertBody([
            'amount' => '150',
        ], $input);

        http_response_code(201);
        echo json_encode([
            'data' => [
                ...$invoice,
                'amount_paid' => '150.000000000000000000',
                'amount_due' => '0.000000000000000000',
                'amount_overpaid' => '1.000000000000000000',
                'reference_id' => null,
                'status' => 'paid',
                'fully_paid_at' => '2026-06-15T00:00:00.000Z',
            ],
            'meta' => ['result' => 'created'],
        ]);
        return;
    }

    assertBody([
        'amount' => '149',
        'reference_id' => 'test_payment_001',
    ], $input);

    http_response_code(201);
    echo json_encode([
        'data' => [
            ...$invoice,
            'status' => 'paid',
            'amount_paid' => '149.000000000000000000',
            'amount_due' => '0.000000000000000000',
            'fully_paid_at' => '2026-06-15T00:00:00.000Z',
        ],
        'meta' => ['result' => 'created'],
    ]);
    return;
}

http_response_code(404);
echo json_encode(['code' => 'not_found', 'message' => 'Not found.']);

/**
 * @param array<string, mixed> $expected
 * @param mixed $actual
 */
function assertBody(array $expected, mixed $actual): void
{
    if (!sameJsonValue($expected, $actual)) {
        failContract('unexpected_body', [
            'expected' => $expected,
            'actual' => $actual,
        ]);
    }
}

function sameJsonValue(mixed $expected, mixed $actual): bool
{
    if (!is_array($expected) || !is_array($actual)) {
        return $expected === $actual;
    }

    if (array_is_list($expected) || array_is_list($actual)) {
        if (!array_is_list($expected) || !array_is_list($actual) || count($expected) !== count($actual)) {
            return false;
        }

        foreach ($expected as $index => $value) {
            if (!sameJsonValue($value, $actual[$index])) {
                return false;
            }
        }

        return true;
    }

    if (array_diff_key($expected, $actual) !== [] || array_diff_key($actual, $expected) !== []) {
        return false;
    }

    foreach ($expected as $key => $value) {
        if (!sameJsonValue($value, $actual[$key])) {
            return false;
        }
    }

    return true;
}

function assertHeader(string $name, string $expected): void
{
    if (($_SERVER[$name] ?? null) !== $expected) {
        failContract('unexpected_header', [
            'header' => $name,
            'expected' => $expected,
            'actual' => $_SERVER[$name] ?? null,
        ]);
    }
}

function assertHeaderStartsWith(string $name, string $expectedPrefix): void
{
    $actual = $_SERVER[$name] ?? null;

    if (!is_string($actual) || !str_starts_with($actual, $expectedPrefix)) {
        failContract('unexpected_header', [
            'header' => $name,
            'expected_prefix' => $expectedPrefix,
            'actual' => $actual,
        ]);
    }
}

function assertNoHeader(string $name): void
{
    if (isset($_SERVER[$name])) {
        failContract('unexpected_header', [
            'header' => $name,
            'actual' => $_SERVER[$name],
        ]);
    }
}

/**
 * @param array<string, mixed> $meta
 */
function failContract(string $code, array $meta): never
{
    http_response_code(500);
    echo json_encode([
        'code' => $code,
        'message' => 'Test request contract mismatch.',
        'meta' => $meta,
    ]);
    exit;
}

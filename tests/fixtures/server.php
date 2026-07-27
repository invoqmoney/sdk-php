<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$body = file_get_contents('php://input') ?: '';
$input = $body === '' ? [] : json_decode($body, true);

// The create shape, exactly its 14 wire keys. Test mode issues no routes and
// has no payment window.
$invoice = [
    'id' => 'inv_test_123',
    'mode' => 'test',
    'amount' => '149.0000',
    'currency' => 'USD',
    'reference_id' => 'order_123',
    'description' => 'Test order',
    'return_url' => 'https://merchant.test/thanks',
    'status' => 'unpaid',
    'checkout_status' => 'unavailable',
    'payment_revision' => 0,
    'amount_due' => '149.000000000000000000',
    'amount_overpaid' => '0.000000000000000000',
    'monitoring_ends_at' => null,
    'payment_options' => [],
];

// The public read of that test invoice: the create shape plus project,
// amount_paid, and transfers, minus reference_id.
$publicTestInvoice = $invoice;
unset($publicTestInvoice['reference_id']);
$publicTestInvoice['project'] = [
    'id' => 'proj_test_123',
    'name' => 'Test project',
    'logo_url' => null,
];
$publicTestInvoice['amount_paid'] = '0.000000000000000000';
$publicTestInvoice['transfers'] = [];

// The public read of a live invoice, carrying every payment option variant and
// one confirmed transfer.
$publicLiveInvoice = [
    'id' => 'inv_live_123',
    'mode' => 'live',
    'amount' => '149.0000',
    'currency' => 'USD',
    'description' => 'Test order',
    'return_url' => 'https://merchant.test/thanks',
    'project' => [
        'id' => 'proj_test_123',
        'name' => 'Test project',
        'logo_url' => null,
    ],
    'status' => 'partially_paid',
    'checkout_status' => 'open',
    'payment_revision' => 1,
    'amount_paid' => '49.000000000000000000',
    'amount_due' => '100.000000000000000000',
    'amount_overpaid' => '0.000000000000000000',
    'transfers' => [[
        'chain_namespace' => 'eip155',
        'chain_reference' => '8453',
        'transaction_id' => '0x5c0b3a2e8f4d6a1b9c7e0d2f4a6b8c0d2e4f6a8b0c2d4e6f8a0b2c4d6e8f0a2b',
        'event_index' => 2,
        'amount' => '49.000000000000000000',
        'explorer_transaction_url' => null,
    ]],
    'monitoring_ends_at' => '2026-06-16T00:00:00.000Z',
    'payment_options' => [
        [
            'collection_method' => 'evm_deposit',
            'chain_namespace' => 'eip155',
            'chain_reference' => '8453',
            'currency' => 'USD',
            'token_address' => '0x833589fcd6edb6e08f4c7c32d4f71b54bda02913',
            'token_decimals' => 6,
            'network_label' => 'Base',
            'display_symbol' => 'USDC',
            'logo_url' => null,
            'chain_logo_url' => null,
            'status' => 'ready',
            'deposit_address' => '0x20c124f3919bb502c6126cda5bd6e5287859d5ca',
            'suggested_amount' => '100.000000',
        ],
        [
            'collection_method' => 'direct_exact',
            'chain_namespace' => 'solana',
            'chain_reference' => '5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp',
            'currency' => 'USD',
            'token_address' => 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v',
            'token_decimals' => 6,
            'network_label' => 'Solana',
            'display_symbol' => 'USDC',
            'logo_url' => null,
            'chain_logo_url' => null,
            'status' => 'ready',
            'recipient_address' => 'GmaDrppBC7P5ARKV8g3djiwP89vz1jLK23V2GBjuAEGB',
            'invoice_amount' => '149.000000',
            'matching_increment' => '0.000123',
            'exact_amount' => '149.000123',
        ],
        [
            'collection_method' => 'direct_exact',
            'chain_namespace' => 'tron',
            'chain_reference' => '0x2b6653dc',
            'currency' => 'USD',
            'token_address' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            'token_decimals' => 6,
            'network_label' => 'TRON',
            'display_symbol' => 'USDT',
            'logo_url' => null,
            'chain_logo_url' => null,
            'status' => 'unavailable',
        ],
    ],
];

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
        ], $input);

        header('Content-Type: text/html');
        http_response_code(502);
        echo '<html>bad gateway</html>';
        return;
    }

    if (($input['amount'] ?? null) === '302') {
        assertBody([
            'amount' => '302',
        ], $input);

        header('Location: /v1/redirect-target');
        http_response_code(302);
        echo json_encode(['code' => 'redirect', 'message' => 'Redirect.']);
        return;
    }

    if (($input['amount'] ?? null) === '150') {
        assertBody([
            'amount' => '150',
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

    // The create body is exactly these four keys: the API schema is strict and
    // rejects anything else with 400 invalid_request / unknown_field.
    assertBody([
        'amount' => '149',
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

    echo json_encode(['data' => $publicTestInvoice]);
    return;
}

if ($method === 'GET' && $path === '/v1/invoices/inv_live_123') {
    assertHeader('HTTP_ACCEPT', 'application/json');
    assertHeaderStartsWith('HTTP_USER_AGENT', 'invoq-php/');
    assertNoHeader('CONTENT_TYPE');
    assertBody([], $input);

    echo json_encode(['data' => $publicLiveInvoice]);
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
                'status' => 'paid',
                'checkout_status' => 'paid',
                'payment_revision' => 1,
                'amount_paid' => '150.000000000000000000',
                'amount_due' => '0.000000000000000000',
                'amount_overpaid' => '1.000000000000000000',
                'reference_id' => null,
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

    // The create shape plus amount_paid and fully_paid_at.
    http_response_code(201);
    echo json_encode([
        'data' => [
            ...$invoice,
            'status' => 'paid',
            'checkout_status' => 'paid',
            'payment_revision' => 1,
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

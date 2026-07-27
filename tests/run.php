<?php

declare(strict_types=1);

use Invoq\Invoq;
use Invoq\InvoqApiError;
use Invoq\InvoqError;
use Invoq\InvoqSignatureVerificationError;
use function Invoq\isInvoicePaid;
use function Invoq\isInvoicePaymentReversed;
use function Invoq\verifyWebhook;

require __DIR__ . '/../vendor/autoload.php';

testUserAgentVersion();

$server = startServer();

try {
    testClient($server['baseUrl']);
    testWebhook();
} finally {
    stopServer($server['process']);
}

echo "All tests passed.\n";

function testUserAgentVersion(): void
{
    runPhpFixture(__DIR__ . '/fixtures/user-agent-dynamic-version.php');
    runPhpFixture(__DIR__ . '/fixtures/user-agent-unknown-version.php');
}

function runPhpFixture(string $path): void
{
    $process = proc_open(
        escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($path),
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes,
    );

    if (!is_resource($process)) {
        fail("Failed to run PHP fixture {$path}.");
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    if ($exitCode !== 0) {
        fail(trim("PHP fixture failed: {$path}\n{$stdout}\n{$stderr}"));
    }
}

/**
 * @return array{baseUrl: string, process: resource}
 */
function startServer(): array
{
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);

    if ($socket === false) {
        fail("Failed to reserve test port: {$errstr}");
    }

    $name = stream_socket_get_name($socket, false);
    fclose($socket);

    if (!is_string($name) || !str_contains($name, ':')) {
        fail('Failed to resolve test port.');
    }

    $port = (int) substr(strrchr($name, ':'), 1);
    $router = __DIR__ . '/fixtures/server.php';
    $command = sprintf('php -S 127.0.0.1:%d %s', $port, escapeshellarg($router));
    $process = proc_open($command, [
        0 => ['pipe', 'r'],
        1 => ['file', '/tmp/invoq-php-test-server.log', 'a'],
        2 => ['file', '/tmp/invoq-php-test-server.log', 'a'],
    ], $pipes);

    if (!is_resource($process)) {
        fail('Failed to start test server.');
    }

    $baseUrl = "http://127.0.0.1:{$port}";
    $deadline = microtime(true) + 5;

    do {
        $curl = curl_init($baseUrl . '/__ready');
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => 200,
        ]);
        curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        if ($status > 0) {
            return ['baseUrl' => $baseUrl, 'process' => $process];
        }

        usleep(50_000);
    } while (microtime(true) < $deadline);

    stopServer($process);
    fail('Test server did not start.');
}

/**
 * @param resource $process
 */
function stopServer($process): void
{
    proc_terminate($process);
    proc_close($process);
}

function testClient(string $baseUrl): void
{
    expectThrows(fn () => new Invoq(''), InvoqError::class);
    expectThrows(fn () => new Invoq('sk_test_123', ['apiOrigin' => 'ftp://api.test']), InvoqError::class);
    expectThrows(fn () => new Invoq('sk_test_123', ['apiOrigin' => 'https://api.test/api']), InvoqError::class);
    expectThrows(fn () => new Invoq('sk_test_123', ['apiOrigin' => 'https://api.test/v1']), InvoqError::class);
    expectThrows(fn () => new Invoq('sk_test_123', ['apiOrigin' => 'https://user:pass@api.test']), InvoqError::class);
    expectThrows(fn () => new Invoq('sk_test_123', ['timeoutMs' => 0]), InvoqError::class);
    expectThrows(fn () => new Invoq('sk_test_123', ['timeoutMs' => 1.5]), InvoqError::class);
    expectThrows(fn () => new Invoq('sk_test_123', ['timeoutMs' => 5_000_000_000]), InvoqError::class);

    $client = new Invoq('sk_test_123', ['apiOrigin' => $baseUrl]);

    $invoice = $client->invoices->create([
        'amount' => '149',
        'description' => 'Test order',
        'reference_id' => 'order_123',
        'return_url' => 'https://merchant.test/thanks',
    ]);

    sameKeys([
        'id',
        'mode',
        'amount',
        'currency',
        'reference_id',
        'description',
        'return_url',
        'status',
        'checkout_status',
        'payment_revision',
        'amount_due',
        'amount_overpaid',
        'monitoring_ends_at',
        'payment_options',
    ], $invoice);
    same('inv_test_123', $invoice['id']);
    same('149.0000', $invoice['amount']);
    same('unpaid', $invoice['status']);
    same('unavailable', $invoice['checkout_status']);
    same(0, $invoice['payment_revision']);
    same('https://merchant.test/thanks', $invoice['return_url']);
    same('0.000000000000000000', $invoice['amount_overpaid']);
    same(null, $invoice['monitoring_ends_at']);
    same([], $invoice['payment_options']);

    // Regression: 0.2.0 put `currency` on the create body, which the strict API
    // schema rejects with 400 invalid_request / unknown_field. Extra input keys
    // must be dropped here, never forwarded — the test server fails the request
    // when the body carries anything but the four request fields.
    $invoiceFromExtraInput = $client->invoices->create([
        'amount' => '149',
        'currency' => 'USD',
        'mode' => 'live',
        'extra' => ['nested' => true],
        'description' => 'Test order',
        'reference_id' => 'order_123',
        'return_url' => 'https://merchant.test/thanks',
    ]);
    same('inv_test_123', $invoiceFromExtraInput['id']);

    $invoiceWithoutOptionalStrings = $client->invoices->create([
        'amount' => '150',
    ]);
    same(null, $invoiceWithoutOptionalStrings['description']);
    same(null, $invoiceWithoutOptionalStrings['reference_id']);
    same(null, $invoiceWithoutOptionalStrings['return_url']);
    same('150.0000', $invoiceWithoutOptionalStrings['amount']);
    same('150.000000000000000000', $invoiceWithoutOptionalStrings['amount_due']);

    $invoiceWithNullReturnUrl = $client->invoices->create([
        'amount' => '151',
        'return_url' => null,
    ]);
    same(null, $invoiceWithNullReturnUrl['return_url']);
    same('151.0000', $invoiceWithNullReturnUrl['amount']);
    same('151.000000000000000000', $invoiceWithNullReturnUrl['amount_due']);

    $publicInvoiceKeys = [
        'id',
        'mode',
        'amount',
        'currency',
        'description',
        'return_url',
        'project',
        'status',
        'checkout_status',
        'payment_revision',
        'amount_paid',
        'amount_due',
        'amount_overpaid',
        'transfers',
        'monitoring_ends_at',
        'payment_options',
    ];

    $fetched = $client->invoices->get('inv_test_123');
    sameKeys($publicInvoiceKeys, $fetched);
    same('inv_test_123', $fetched['id']);
    same('149.0000', $fetched['amount']);
    same('https://merchant.test/thanks', $fetched['return_url']);
    same(false, array_key_exists('reference_id', $fetched));
    sameKeys(['id', 'name', 'logo_url'], $fetched['project']);
    same('Test project', $fetched['project']['name']);
    same('0.000000000000000000', $fetched['amount_paid']);
    same('149.000000000000000000', $fetched['amount_due']);
    same('unavailable', $fetched['checkout_status']);
    same('0.000000000000000000', $fetched['amount_overpaid']);
    same(null, $fetched['monitoring_ends_at']);
    same([], $fetched['transfers']);
    same([], $fetched['payment_options']);

    // The live read carries the issued route set and the receipt trail.
    $fetchedLive = $client->invoices->get('inv_live_123');
    sameKeys($publicInvoiceKeys, $fetchedLive);
    same('open', $fetchedLive['checkout_status']);
    same(1, $fetchedLive['payment_revision']);
    same('2026-06-16T00:00:00.000Z', $fetchedLive['monitoring_ends_at']);

    sameKeys([
        'chain_namespace',
        'chain_reference',
        'transaction_id',
        'event_index',
        'amount',
        'explorer_transaction_url',
    ], $fetchedLive['transfers'][0]);
    same(2, $fetchedLive['transfers'][0]['event_index']);
    same('49.000000000000000000', $fetchedLive['transfers'][0]['amount']);
    same(null, $fetchedLive['transfers'][0]['explorer_transaction_url']);

    $paymentOptionKeys = [
        'collection_method',
        'chain_namespace',
        'chain_reference',
        'currency',
        'token_address',
        'token_decimals',
        'network_label',
        'display_symbol',
        'logo_url',
        'chain_logo_url',
        'status',
    ];

    same(3, count($fetchedLive['payment_options']));

    // Ready evm_deposit, ready direct_exact, and unavailable: the common
    // fields plus exactly what each variant adds.
    sameKeys([
        ...$paymentOptionKeys,
        'deposit_address',
        'suggested_amount',
    ], $fetchedLive['payment_options'][0]);
    same('evm_deposit', $fetchedLive['payment_options'][0]['collection_method']);
    same('ready', $fetchedLive['payment_options'][0]['status']);
    same(6, $fetchedLive['payment_options'][0]['token_decimals']);
    same('100.000000', $fetchedLive['payment_options'][0]['suggested_amount']);

    sameKeys([
        ...$paymentOptionKeys,
        'recipient_address',
        'invoice_amount',
        'matching_increment',
        'exact_amount',
    ], $fetchedLive['payment_options'][1]);
    same('direct_exact', $fetchedLive['payment_options'][1]['collection_method']);
    same('solana', $fetchedLive['payment_options'][1]['chain_namespace']);
    same('149.000123', $fetchedLive['payment_options'][1]['exact_amount']);

    sameKeys($paymentOptionKeys, $fetchedLive['payment_options'][2]);
    same('unavailable', $fetchedLive['payment_options'][2]['status']);
    same('tron', $fetchedLive['payment_options'][2]['chain_namespace']);

    $paidInvoice = $client->invoices->createTestPayment('inv_test_123', [
        'amount' => '149',
        'reference_id' => 'test_payment_001',
    ]);
    sameKeys([
        'id',
        'mode',
        'amount',
        'currency',
        'reference_id',
        'description',
        'return_url',
        'status',
        'checkout_status',
        'payment_revision',
        'amount_paid',
        'amount_due',
        'amount_overpaid',
        'monitoring_ends_at',
        'payment_options',
        'fully_paid_at',
    ], $paidInvoice);
    same('paid', $paidInvoice['status']);
    same('paid', $paidInvoice['checkout_status']);
    same(1, $paidInvoice['payment_revision']);
    same('149.000000000000000000', $paidInvoice['amount_paid']);
    same('0.000000000000000000', $paidInvoice['amount_due']);
    same('0.000000000000000000', $paidInvoice['amount_overpaid']);
    same('2026-06-15T00:00:00.000Z', $paidInvoice['fully_paid_at']);

    // Extra keys are dropped on this body too.
    $paidInvoiceWithoutReference = $client->invoices->createTestPayment('inv_test_123', [
        'amount' => '150',
        'currency' => 'USD',
        'extra' => null,
    ]);
    same('paid', $paidInvoiceWithoutReference['status']);
    same('150.000000000000000000', $paidInvoiceWithoutReference['amount_paid']);
    same(null, $paidInvoiceWithoutReference['reference_id']);
    same('1.000000000000000000', $paidInvoiceWithoutReference['amount_overpaid']);

    expectThrowsExactly(
        fn () => $client->invoices->create([
            'amount' => '149',
            'description' => null,
        ]),
        InvoqError::class,
    );
    expectThrowsExactly(
        fn () => $client->invoices->create([
            'amount' => '149',
            'reference_id' => null,
        ]),
        InvoqError::class,
    );
    expectThrowsExactly(
        fn () => $client->invoices->create([
            'amount' => '149',
            'return_url' => 42,
        ]),
        InvoqError::class,
    );
    expectThrowsExactly(
        fn () => $client->invoices->create(['amount' => '']),
        InvoqError::class,
    );
    expectThrowsExactly(
        fn () => $client->invoices->create(['amount' => '  ']),
        InvoqError::class,
    );
    expectThrowsExactly(
        fn () => $client->invoices->create(['amount' => 149]),
        InvoqError::class,
    );
    expectThrowsExactly(
        fn () => $client->invoices->get(''),
        InvoqError::class,
    );
    expectThrowsExactly(
        fn () => $client->invoices->get('  '),
        InvoqError::class,
    );
    expectThrowsExactly(
        fn () => $client->invoices->createTestPayment('', ['amount' => '149']),
        InvoqError::class,
    );
    expectThrowsExactly(
        fn () => $client->invoices->createTestPayment('inv_test_123', ['amount' => '']),
        InvoqError::class,
    );
    expectThrowsExactly(
        fn () => $client->invoices->createTestPayment('inv_test_123', [
            'amount' => '149',
            'reference_id' => null,
        ]),
        InvoqError::class,
    );

    try {
        $client->invoices->create(['amount' => '0.001']);
        fail('Expected InvoqApiError.');
    } catch (InvoqApiError $error) {
        same(400, $error->status);
        same('invalid_amount', $error->getApiCode());
        same('invalid_amount', $error->code);
        same('0.01', $error->meta['min_amount'] ?? null);
        same('amount', $error->fields[0]['field'] ?? null);
        same('amount_too_small', $error->fields[0]['code'] ?? null);
        same('Amount must be greater than or equal to 0.01.', $error->fields[0]['message'] ?? null);
    }

    try {
        $client->invoices->create(['amount' => '502']);
        fail('Expected non-JSON InvoqApiError.');
    } catch (InvoqApiError $error) {
        same(502, $error->status);
        same('<html>bad gateway</html>', $error->payload);
    }

    try {
        $client->invoices->create(['amount' => '302']);
        fail('Expected redirect InvoqApiError.');
    } catch (InvoqApiError $error) {
        same(302, $error->status);
        same('redirect', $error->getApiCode());
    }
}

function testWebhook(): void
{
    $secret = 'whsec_test_123';
    $timestamp = time();
    // An event type this version does not model: verification is shape-agnostic,
    // so a new backend event never fails on an older SDK.
    $body = '{"id":"evt_test","type":"invoice.future_event","data":{"invoice":{"id":"inv_test"}}}';
    $signature = hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    $header = "t={$timestamp},v1={$signature}";

    $event = verifyWebhook($body, ['Invoq-Signature' => [$header]], $secret);
    same('evt_test', $event['id']);
    same('invoice.future_event', $event['type']);
    same(false, isInvoicePaid($event));
    same(false, isInvoicePaymentReversed($event));

    expectSignatureError(
        fn () => verifyWebhook($body, [], $secret),
        'missing_signature',
    );

    expectSignatureError(
        fn () => verifyWebhook($body, ['invoq-signature' => $header], 'wrong'),
        'signature_mismatch',
    );

    $paidInvoice = [
        'id' => 'inv_test',
        'mode' => 'test',
        'status' => 'paid',
        'amount' => '149.0000',
        'currency' => 'USD',
        'amount_paid' => '149.000000000000000000',
        'reference_id' => 'order_123',
        'payment_revision' => 1,
        'fully_paid_at' => '2026-06-15T00:00:00.000Z',
    ];

    // The same invoice after a credited transfer was reversed.
    $reversedInvoice = [
        ...$paidInvoice,
        'status' => 'partially_paid',
        'amount_paid' => '20.000000000000000000',
        'payment_revision' => 2,
        'fully_paid_at' => null,
    ];

    // Every field the shared envelope check requires of data.invoice.
    $invoiceFields = [
        'id',
        'mode',
        'status',
        'amount',
        'currency',
        'amount_paid',
        'reference_id',
        'payment_revision',
        'fully_paid_at',
    ];

    foreach (['paid', 'settling', 'settled'] as $status) {
        same(true, isInvoicePaid(lifecycleEvent('invoice.paid', [
            ...$paidInvoice,
            'status' => $status,
        ])));
    }

    // Paid-equivalent statuses only, and never a status this version cannot
    // recognize: the paid guard has to fail closed.
    foreach (['review_required', 'partially_paid', 'unexpected'] as $status) {
        same(false, isInvoicePaid(lifecycleEvent('invoice.paid', [
            ...$paidInvoice,
            'status' => $status,
        ])));
    }

    // Both guards check the same invoice shape before answering.
    foreach ($invoiceFields as $field) {
        $paidWithout = $paidInvoice;
        $reversedWithout = $reversedInvoice;
        unset($paidWithout[$field], $reversedWithout[$field]);

        same(false, isInvoicePaid(lifecycleEvent('invoice.paid', $paidWithout)));
        same(false, isInvoicePaymentReversed(
            lifecycleEvent('invoice.payment_reversed', $reversedWithout),
        ));
    }

    same(false, isInvoicePaid(lifecycleEvent('invoice.paid', [
        ...$paidInvoice,
        'payment_revision' => '1',
    ])));
    same(false, isInvoicePaymentReversed(lifecycleEvent('invoice.payment_reversed', [
        ...$reversedInvoice,
        'payment_revision' => 2.5,
    ])));

    foreach (['id', 'mode', 'created_at', 'data'] as $field) {
        $event = lifecycleEvent('invoice.paid', $paidInvoice);
        unset($event[$field]);

        same(false, isInvoicePaid($event));
    }

    // The reversal guard must not fail closed the way the paid guard does:
    // dropping a reversal leaves an order fulfilled on a payment that no longer
    // exists, so an unknown status still has to come through.
    foreach (
        ['unpaid', 'partially_paid', 'review_required', 'paid', 'settling', 'settled', 'unexpected']
        as $status
    ) {
        same(true, isInvoicePaymentReversed(lifecycleEvent('invoice.payment_reversed', [
            ...$reversedInvoice,
            'status' => $status,
        ])));
    }

    // Neither guard answers for the other event type.
    same(false, isInvoicePaid(lifecycleEvent('invoice.payment_reversed', $reversedInvoice)));
    same(false, isInvoicePaid(lifecycleEvent('invoice.payment_reversed', [
        ...$reversedInvoice,
        'status' => 'paid',
    ])));
    same(false, isInvoicePaymentReversed(lifecycleEvent('invoice.paid', $paidInvoice)));

    // The documented path: verify, then branch.
    $reversedBody = json_encode(lifecycleEvent('invoice.payment_reversed', $reversedInvoice));
    $reversedSignature = hash_hmac('sha256', $timestamp . '.' . $reversedBody, $secret);
    $reversed = verifyWebhook(
        $reversedBody,
        ['invoq-signature' => "t={$timestamp},v1={$reversedSignature}"],
        $secret,
    );

    same(true, isInvoicePaymentReversed($reversed));
    same(2, $reversed['data']['invoice']['payment_revision']);
    same(null, $reversed['data']['invoice']['fully_paid_at']);
}

/**
 * @param array<string, mixed> $invoice
 * @return array<string, mixed>
 */
function lifecycleEvent(string $type, array $invoice): array
{
    return [
        'id' => 'wdel_test',
        'type' => $type,
        'mode' => 'test',
        'created_at' => '2026-06-15T00:00:00.000Z',
        'data' => ['invoice' => $invoice],
    ];
}

/**
 * @param callable(): mixed $callback
 */
function expectThrows(callable $callback, string $class): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        if ($error instanceof $class) {
            return;
        }

        throw $error;
    }

    fail("Expected {$class}.");
}

/**
 * @param callable(): mixed $callback
 */
function expectThrowsExactly(callable $callback, string $class): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        if (get_class($error) === $class) {
            return;
        }

        throw $error;
    }

    fail("Expected {$class}.");
}

/**
 * @param callable(): mixed $callback
 */
function expectSignatureError(callable $callback, string $code): void
{
    try {
        $callback();
    } catch (InvoqSignatureVerificationError $error) {
        same($code, $error->getSignatureCode());
        same($code, $error->code);
        return;
    }

    fail("Expected signature error {$code}.");
}

/**
 * @param list<string> $expected
 * @param array<string, mixed> $actual
 */
function sameKeys(array $expected, array $actual): void
{
    $expectedKeys = $expected;
    $actualKeys = array_keys($actual);
    sort($expectedKeys);
    sort($actualKeys);

    same($expectedKeys, $actualKeys);
}

function same(mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        fail('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . '.');
    }
}

function fail(string $message): never
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

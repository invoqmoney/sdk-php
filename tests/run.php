<?php

declare(strict_types=1);

use Invoq\Invoq;
use Invoq\InvoqApiError;
use Invoq\InvoqError;
use Invoq\InvoqSignatureVerificationError;
use function Invoq\isInvoicePaid;
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
        'currency' => 'USD',
        'description' => 'Test order',
        'reference_id' => 'order_123',
        'return_url' => 'https://merchant.test/thanks',
    ]);

    same('inv_test_123', $invoice['id']);
    same('149.0000', $invoice['amount']);
    same('unpaid', $invoice['status']);
    same('https://merchant.test/thanks', $invoice['return_url']);
    same('0.000000000000000000', $invoice['amount_overpaid']);
    same(null, $invoice['monitoring_status']);
    same(false, array_key_exists('transfers', $invoice));

    $invoiceWithoutOptionalStrings = $client->invoices->create([
        'amount' => '150',
        'currency' => 'USD',
        'extra' => null,
    ]);
    same(null, $invoiceWithoutOptionalStrings['description']);
    same(null, $invoiceWithoutOptionalStrings['reference_id']);
    same(null, $invoiceWithoutOptionalStrings['return_url']);
    same('150.0000', $invoiceWithoutOptionalStrings['amount']);
    same('150.000000000000000000', $invoiceWithoutOptionalStrings['amount_due']);

    $invoiceWithNullReturnUrl = $client->invoices->create([
        'amount' => '151',
        'currency' => 'USD',
        'return_url' => null,
    ]);
    same(null, $invoiceWithNullReturnUrl['return_url']);
    same('151.0000', $invoiceWithNullReturnUrl['amount']);
    same('151.000000000000000000', $invoiceWithNullReturnUrl['amount_due']);

    $fetched = $client->invoices->get('inv_test_123');
    same('inv_test_123', $fetched['id']);
    same('149.0000', $fetched['amount']);
    same('https://merchant.test/thanks', $fetched['return_url']);
    same(false, array_key_exists('reference_id', $fetched));
    same('Test project', $fetched['project']['name'] ?? null);
    same('0.000000000000000000', $fetched['amount_paid']);
    same('149.000000000000000000', $fetched['amount_due']);
    same('unpaid', $fetched['payment_status']);
    same('0.000000000000000000', $fetched['amount_overpaid']);
    same(null, $fetched['monitoring_status']);
    same([], $fetched['transfers']);

    $paidInvoice = $client->invoices->createTestPayment('inv_test_123', [
        'amount' => '149',
        'reference_id' => 'test_payment_001',
    ]);
    same('paid', $paidInvoice['status']);
    same('149.000000000000000000', $paidInvoice['amount_paid']);
    same('0.000000000000000000', $paidInvoice['amount_due']);
    same('0.000000000000000000', $paidInvoice['amount_overpaid']);

    $paidInvoiceWithoutReference = $client->invoices->createTestPayment('inv_test_123', [
        'amount' => '150',
        'extra' => null,
    ]);
    same('paid', $paidInvoiceWithoutReference['status']);
    same('150.000000000000000000', $paidInvoiceWithoutReference['amount_paid']);
    same(null, $paidInvoiceWithoutReference['reference_id']);
    same('1.000000000000000000', $paidInvoiceWithoutReference['amount_overpaid']);

    expectThrowsExactly(
        fn () => $client->invoices->create([
            'amount' => '149',
            'currency' => 'USD',
            'description' => null,
        ]),
        InvoqError::class,
    );
    expectThrowsExactly(
        fn () => $client->invoices->create([
            'amount' => '149',
            'currency' => 'USD',
            'reference_id' => null,
        ]),
        InvoqError::class,
    );
    expectThrowsExactly(
        fn () => $client->invoices->create([
            'amount' => '149',
            'currency' => 'USD',
            'return_url' => 42,
        ]),
        InvoqError::class,
    );
    expectThrowsExactly(
        fn () => $client->invoices->create(['amount' => '', 'currency' => 'USD']),
        InvoqError::class,
    );
    expectThrowsExactly(
        fn () => $client->invoices->create(['amount' => '  ', 'currency' => 'USD']),
        InvoqError::class,
    );
    expectThrowsExactly(
        fn () => $client->invoices->create(['amount' => 149, 'currency' => 'USD']),
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
        $client->invoices->create(['amount' => '0.001', 'currency' => 'USD']);
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
        $client->invoices->create(['amount' => '502', 'currency' => 'USD']);
        fail('Expected non-JSON InvoqApiError.');
    } catch (InvoqApiError $error) {
        same(502, $error->status);
        same('<html>bad gateway</html>', $error->payload);
    }

    try {
        $client->invoices->create(['amount' => '302', 'currency' => 'USD']);
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
    $body = '{"id":"evt_test","type":"webhook.ping","data":{"project":{"id":"proj_test"}}}';
    $signature = hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    $header = "t={$timestamp},v1={$signature}";

    $event = verifyWebhook($body, ['Invoq-Signature' => [$header]], $secret);
    same('evt_test', $event['id']);

    expectSignatureError(
        fn () => verifyWebhook($body, [], $secret),
        'missing_signature',
    );

    expectSignatureError(
        fn () => verifyWebhook($body, ['invoq-signature' => $header], 'wrong'),
        'signature_mismatch',
    );

    $paid = [
        'id' => 'evt_paid',
        'type' => 'invoice.paid',
        'mode' => 'test',
        'created_at' => '2026-06-15T00:00:00.000Z',
        'data' => [
            'invoice' => [
                'id' => 'inv_test',
                'mode' => 'test',
                'status' => 'paid',
                'amount' => '149.0000',
                'currency' => 'USD',
                'amount_paid' => '149.000000000000000000',
                'reference_id' => 'order_123',
                'fully_paid_at' => '2026-06-15T00:00:00.000Z',
            ],
        ],
    ];

    same(true, isInvoicePaid($paid));

    foreach (['settling', 'settled'] as $status) {
        $paid['data']['invoice']['status'] = $status;
        same(true, isInvoicePaid($paid));
    }

    $paid['data']['invoice']['status'] = 'review_required';
    same(false, isInvoicePaid($paid));

    $paid['data']['invoice']['status'] = 'paid';
    unset($paid['data']['invoice']['amount_paid']);
    same(false, isInvoicePaid($paid));
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

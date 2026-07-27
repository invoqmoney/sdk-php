<?php

declare(strict_types=1);

namespace Invoq;

use Invoq\Internal\Request;

final class InvoicesResource
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $apiOrigin,
        private readonly int $timeoutMs,
    ) {
    }

    /**
     * @param array{amount: string, description?: string, reference_id?: string, return_url?: string|null} $input
     * @return array<string, mixed>
     */
    public function create(array $input): array
    {
        return Request::json(
            apiKey: $this->apiKey,
            apiOrigin: $this->apiOrigin,
            timeoutMs: $this->timeoutMs,
            path: '/v1/invoices',
            body: self::createInvoiceRequestBody($input),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $invoiceId): array
    {
        $id = self::requiredRequestString($invoiceId, 'invoiceId');

        return Request::json(
            apiKey: $this->apiKey,
            apiOrigin: $this->apiOrigin,
            timeoutMs: $this->timeoutMs,
            method: 'GET',
            path: '/v1/invoices/' . rawurlencode($id),
        );
    }

    /**
     * @param array{amount: string, reference_id?: string} $input
     * @return array<string, mixed>
     */
    public function createTestPayment(string $invoiceId, array $input): array
    {
        $id = self::requiredRequestString($invoiceId, 'invoiceId');

        return Request::json(
            apiKey: $this->apiKey,
            apiOrigin: $this->apiOrigin,
            timeoutMs: $this->timeoutMs,
            path: '/v1/invoices/' . rawurlencode($id) . '/test-payments',
            body: self::createTestPaymentRequestBody($input),
        );
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private static function createInvoiceRequestBody(array $input): array
    {
        // Only these four fields exist: the API rejects unknown body keys, and
        // currency (always USD) and mode (from the key) are not request fields.
        // Anything else the caller passes is dropped here, not forwarded.
        $body = [
            'amount' => self::requiredRequestString($input['amount'] ?? null, 'amount'),
        ];

        self::copyOptionalStringField($input, $body, 'description');
        self::copyOptionalStringField($input, $body, 'reference_id');
        self::copyOptionalNullableStringField($input, $body, 'return_url');

        return $body;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private static function createTestPaymentRequestBody(array $input): array
    {
        $body = [
            'amount' => self::requiredRequestString($input['amount'] ?? null, 'amount'),
        ];

        self::copyOptionalStringField($input, $body, 'reference_id');

        return $body;
    }

    private static function requiredRequestString(mixed $value, string $field): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new InvoqError("{$field} must be a non-empty string.");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $body
     */
    private static function copyOptionalStringField(
        array $input,
        array &$body,
        string $field,
    ): void {
        if (!array_key_exists($field, $input)) {
            return;
        }

        if (!is_string($input[$field])) {
            throw new InvoqError("{$field} must be a string when provided.");
        }

        $body[$field] = $input[$field];
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $body
     */
    private static function copyOptionalNullableStringField(
        array $input,
        array &$body,
        string $field,
    ): void {
        if (!array_key_exists($field, $input)) {
            return;
        }

        if (!is_string($input[$field]) && $input[$field] !== null) {
            throw new InvoqError("{$field} must be a string or null when provided.");
        }

        $body[$field] = $input[$field];
    }
}

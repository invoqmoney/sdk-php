<?php

declare(strict_types=1);

namespace Invoq;

class Invoq
{
    private const DEFAULT_API_ORIGIN = 'https://api.invoq.money';
    private const DEFAULT_TIMEOUT_MS = 10_000;
    private const MAX_TIMEOUT_MS = 4_294_967_295;

    public readonly InvoicesResource $invoices;

    private readonly string $apiKey;
    private readonly string $apiOrigin;
    private readonly int $timeoutMs;

    /**
     * @param array{apiOrigin?: string, timeoutMs?: int} $options
     */
    public function __construct(string $apiKey, array $options = [])
    {
        if (trim($apiKey) === '') {
            throw new InvoqError('invoq API key must be a non-empty string.');
        }

        $apiOrigin = $options['apiOrigin']
            ?? self::DEFAULT_API_ORIGIN;
        $timeoutMs = $options['timeoutMs']
            ?? self::DEFAULT_TIMEOUT_MS;

        if (!is_string($apiOrigin)) {
            throw new InvoqError('apiOrigin must be an absolute http or https origin.');
        }

        $this->apiKey = $apiKey;
        $this->apiOrigin = self::normalizeApiOrigin($apiOrigin);
        $this->timeoutMs = self::normalizeTimeoutMs($timeoutMs);
        $this->invoices = new InvoicesResource($this->apiKey, $this->apiOrigin, $this->timeoutMs);
    }

    private static function normalizeApiOrigin(string $value): string
    {
        $parts = parse_url($value);

        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new InvoqError('apiOrigin must be an absolute http or https origin.');
        }

        $scheme = strtolower($parts['scheme']);

        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new InvoqError('apiOrigin must be an absolute http or https origin.');
        }

        if (isset($parts['query']) || isset($parts['fragment'])) {
            throw new InvoqError('apiOrigin must not include query or hash parts.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvoqError('apiOrigin must not include username or password.');
        }

        $pathname = rtrim($parts['path'] ?? '/', '/') ?: '/';

        if ($pathname !== '/') {
            throw new InvoqError('apiOrigin must not include a path.');
        }

        $authority = $parts['host'];

        if (isset($parts['port'])) {
            $authority .= ':' . $parts['port'];
        }

        return $scheme . '://' . $authority . '/';
    }

    private static function normalizeTimeoutMs(mixed $value): int
    {
        if (!is_int($value) || $value <= 0 || $value > self::MAX_TIMEOUT_MS) {
            throw new InvoqError('timeoutMs must be a positive integer of at most 4294967295.');
        }

        return $value;
    }
}

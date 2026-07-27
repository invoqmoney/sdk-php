<?php

declare(strict_types=1);

namespace Invoq;

/**
 * @param array<string, string|array<int, string>|null>|null $headers
 * @return array<string, mixed>
 */
function verifyWebhook(string $rawBody, ?array $headers, string $webhookSecret): array
{
    return Webhooks::verifyWebhook($rawBody, $headers, $webhookSecret);
}

/**
 * @param array<string, mixed> $event
 */
function isInvoicePaid(array $event): bool
{
    return Webhooks::isInvoicePaid($event);
}

/**
 * @param array<string, mixed> $event
 */
function isInvoicePaymentReversed(array $event): bool
{
    return Webhooks::isInvoicePaymentReversed($event);
}

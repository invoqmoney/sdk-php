<?php

declare(strict_types=1);

namespace Invoq;

use JsonException;

final class Webhooks
{
    private const DEFAULT_TOLERANCE_SECONDS = 300;
    private const SIGNATURE_PATTERN = '/^[a-f0-9]{64}$/i';

    /**
     * @param array<string, string|array<int, string>|null>|null $headers
     * @return array<string, mixed>
     */
    public static function verifyWebhook(string $rawBody, ?array $headers, string $webhookSecret): array
    {
        $signatureHeader = self::getSignatureHeader($headers);

        if ($signatureHeader === null || $signatureHeader === '') {
            throw self::signatureError('missing_signature', 'Missing invoq-signature header.');
        }

        if ($webhookSecret === '') {
            throw self::signatureError(
                'invalid_signature_header',
                'Webhook secret must be a non-empty string.',
            );
        }

        $parsed = self::parseSignatureHeader($signatureHeader);
        $nowSeconds = time();

        if (abs($nowSeconds - $parsed['timestampSeconds']) > self::DEFAULT_TOLERANCE_SECONDS) {
            throw self::signatureError(
                'timestamp_outside_tolerance',
                'Webhook timestamp is outside the allowed tolerance.',
            );
        }

        $expectedSignature = hash_hmac(
            'sha256',
            $parsed['timestamp'] . '.' . $rawBody,
            $webhookSecret,
        );

        if (!hash_equals($expectedSignature, $parsed['signature'])) {
            throw self::signatureError('signature_mismatch', 'Webhook signature mismatch.');
        }

        try {
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw self::signatureError('invalid_payload', 'Webhook payload is not valid JSON.');
        }

        if (!is_array($payload) || !is_string($payload['type'] ?? null)) {
            throw self::signatureError(
                'invalid_payload',
                'Webhook payload must be an object with a string type.',
            );
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $event
     */
    public static function isInvoicePaid(array $event): bool
    {
        $invoice = self::lifecycleEventInvoice($event, 'invoice.paid');

        // Paid-equivalent statuses only: review_required has money against it
        // but is not cleared for fulfillment.
        return $invoice !== null && self::isInvoicePaidStatus($invoice['status'] ?? null);
    }

    /**
     * @param array<string, mixed> $event
     */
    public static function isInvoicePaymentReversed(array $event): bool
    {
        // No status check, unlike the paid guard: rejecting an unrecognized
        // status would drop the event and leave the order fulfilled on a
        // payment that no longer exists.
        return self::lifecycleEventInvoice($event, 'invoice.payment_reversed') !== null;
    }

    /**
     * The fields both lifecycle events share, returned so each guard can apply
     * its own status rule. null when this is not a well-formed event of that
     * type.
     *
     * @param array<string, mixed> $event
     * @return array<string, mixed>|null
     */
    private static function lifecycleEventInvoice(array $event, string $type): ?array
    {
        if (
            ($event['type'] ?? null) !== $type
            || !is_string($event['id'] ?? null)
            || !self::isInvoiceMode($event['mode'] ?? null)
            || !is_string($event['created_at'] ?? null)
            || !is_array($event['data'] ?? null)
            || !is_array($event['data']['invoice'] ?? null)
        ) {
            return null;
        }

        $invoice = $event['data']['invoice'];

        $valid = is_string($invoice['id'] ?? null)
            && self::isInvoiceMode($invoice['mode'] ?? null)
            && is_string($invoice['status'] ?? null)
            && is_string($invoice['amount'] ?? null)
            && ($invoice['currency'] ?? null) === 'USD'
            && is_string($invoice['amount_paid'] ?? null)
            && array_key_exists('reference_id', $invoice)
            && (is_string($invoice['reference_id']) || $invoice['reference_id'] === null)
            && is_int($invoice['payment_revision'] ?? null)
            && array_key_exists('fully_paid_at', $invoice)
            && (is_string($invoice['fully_paid_at']) || $invoice['fully_paid_at'] === null);

        return $valid ? $invoice : null;
    }

    /**
     * @return array{timestamp: string, timestampSeconds: int, signature: string}
     */
    private static function parseSignatureHeader(string $signatureHeader): array
    {
        $parts = [];

        foreach (explode(',', $signatureHeader) as $part) {
            $separatorIndex = strpos($part, '=');

            if ($separatorIndex === false) {
                throw self::signatureError(
                    'invalid_signature_header',
                    'Invalid invoq-signature header.',
                );
            }

            $key = trim(substr($part, 0, $separatorIndex));
            $value = trim(substr($part, $separatorIndex + 1));

            if ($key !== '' && $value !== '') {
                $parts[$key] = $value;
            }
        }

        $timestamp = $parts['t'] ?? null;
        $signature = $parts['v1'] ?? null;

        if (!is_string($timestamp) || !is_string($signature) || !ctype_digit($timestamp)) {
            throw self::signatureError(
                'invalid_signature_header',
                'Invalid invoq-signature header.',
            );
        }

        if (preg_match(self::SIGNATURE_PATTERN, $signature) !== 1) {
            throw self::signatureError(
                'invalid_signature_header',
                'Invalid invoq-signature signature.',
            );
        }

        return [
            'timestamp' => $timestamp,
            'timestampSeconds' => (int) $timestamp,
            'signature' => strtolower($signature),
        ];
    }

    /**
     * @param array<string, string|array<int, string>|null>|null $headers
     */
    private static function getSignatureHeader(?array $headers): ?string
    {
        if ($headers === null) {
            return null;
        }

        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) !== 'invoq-signature') {
                continue;
            }

            if ($value === null) {
                return null;
            }

            if (is_array($value)) {
                return implode(',', array_map(static fn ($item): string => (string) $item, $value));
            }

            return (string) $value;
        }

        return null;
    }

    private static function isInvoiceMode(mixed $value): bool
    {
        return $value === 'test' || $value === 'live';
    }

    private static function isInvoicePaidStatus(mixed $value): bool
    {
        return $value === 'paid' || $value === 'settling' || $value === 'settled';
    }

    private static function signatureError(string $code, string $message): InvoqSignatureVerificationError
    {
        return new InvoqSignatureVerificationError($code, $message);
    }
}

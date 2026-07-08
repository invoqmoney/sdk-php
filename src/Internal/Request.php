<?php

declare(strict_types=1);

namespace Invoq\Internal;

use CurlHandle;
use JsonException;
use Invoq\InvoqApiError;
use Invoq\InvoqError;

final class Request
{
    private const PACKAGE_NAME = 'invoq/invoq-php';

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    public static function json(
        string $apiKey,
        string $apiOrigin,
        int $timeoutMs,
        string $path,
        string $method = 'POST',
        ?array $body = null,
    ): array {
        $url = $apiOrigin . ltrim($path, '/');
        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $apiKey,
            'User-Agent: ' . self::userAgent(),
        ];
        $bodyText = null;

        if ($body !== null) {
            try {
                $bodyText = json_encode(
                    $body,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
                );
            } catch (JsonException $error) {
                throw new InvoqError('Failed to encode invoq API request.', $error);
            }

            $headers[] = 'Content-Type: application/json';
        }

        [$status, $responseText] = self::send($url, $method, $headers, $bodyText, $timeoutMs);

        try {
            $payload = json_decode($responseText, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            if ($status < 200 || $status >= 300) {
                throw self::apiErrorFromResponse($status, $responseText);
            }

            throw new InvoqError('Failed to parse invoq API response.', $error);
        }

        if ($status < 200 || $status >= 300) {
            throw self::apiErrorFromResponse($status, $payload);
        }

        if (!is_array($payload) || !array_key_exists('data', $payload)) {
            throw new InvoqError(
                'invoq API response did not include a data envelope.',
                payload: $payload,
            );
        }

        if (!is_array($payload['data'])) {
            throw new InvoqError(
                'invoq API response data envelope was not an object.',
                payload: $payload,
            );
        }

        return $payload['data'];
    }

    private static function userAgent(): string
    {
        return 'invoq-php/' . (self::packageVersion() ?? 'unknown');
    }

    private static function packageVersion(): ?string
    {
        if (!@class_exists(\Composer\InstalledVersions::class)) {
            return null;
        }

        try {
            $version = \Composer\InstalledVersions::getPrettyVersion(self::PACKAGE_NAME);
        } catch (\Throwable) {
            return null;
        }

        if (!is_string($version)) {
            return null;
        }

        $version = trim($version);

        if ($version === '' || str_ends_with($version, '+no-version-set')) {
            return null;
        }

        return preg_replace('/^v(?=\d)/i', '', $version) ?? $version;
    }

    /**
     * @param list<string> $headers
     * @return array{0: int, 1: string}
     */
    private static function send(
        string $url,
        string $method,
        array $headers,
        ?string $bodyText,
        int $timeoutMs,
    ): array {
        $curl = curl_init($url);

        if (!$curl instanceof CurlHandle) {
            throw new InvoqError('Failed to connect to invoq API.');
        }

        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => $timeoutMs,
        ];

        if ($bodyText !== null) {
            $options[CURLOPT_POSTFIELDS] = $bodyText;
        }

        curl_setopt_array($curl, $options);
        $responseText = curl_exec($curl);

        if ($responseText === false) {
            $errno = curl_errno($curl);
            $message = curl_error($curl);

            if ($errno === \CURLE_OPERATION_TIMEDOUT) {
                throw new InvoqError('invoq API request timed out.');
            }

            throw new InvoqError(
                $message === ''
                    ? 'Failed to connect to invoq API.'
                    : 'Failed to connect to invoq API: ' . $message,
            );
        }

        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        if (!is_int($status) || $status <= 0) {
            throw new InvoqError('Failed to read invoq API response.');
        }

        return [$status, (string) $responseText];
    }

    private static function apiErrorFromResponse(int $status, mixed $payload): InvoqApiError
    {
        $error = is_array($payload) ? $payload : null;
        $code = is_string($error['code'] ?? null) ? $error['code'] : null;
        $message = is_string($error['message'] ?? null)
            ? $error['message']
            : 'invoq API request failed.';
        $fields = self::parseFields($error['fields'] ?? null);
        $meta = is_array($error['meta'] ?? null) ? $error['meta'] : null;

        return new InvoqApiError($message, [
            'status' => $status,
            'code' => $code,
            'fields' => $fields,
            'meta' => $meta,
            'payload' => $payload,
        ]);
    }

    /**
     * @return array<int, array{field: string, location: string, code: string, message: string}>|null
     */
    private static function parseFields(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $fields = [];

        foreach ($value as $field) {
            if (!is_array($field)) {
                continue;
            }

            $location = $field['location'] ?? null;

            if (!in_array($location, ['query', 'path', 'body', 'header'], true)) {
                continue;
            }

            if (
                !is_string($field['field'] ?? null)
                || !is_string($field['code'] ?? null)
                || !is_string($field['message'] ?? null)
            ) {
                continue;
            }

            $fields[] = [
                'field' => $field['field'],
                'location' => $location,
                'code' => $field['code'],
                'message' => $field['message'],
            ];
        }

        return $fields;
    }
}

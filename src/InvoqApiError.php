<?php

declare(strict_types=1);

namespace Invoq;

class InvoqApiError extends InvoqError
{
    public readonly int $status;
    public readonly ?string $apiCode;

    /**
     * @var array<int, array{field: string, location: string, code: string, message: string}>|null
     */
    public readonly ?array $fields;

    /**
     * @var array<string, mixed>|null
     */
    public readonly ?array $meta;

    /**
     * @param array{
     *     status: int,
     *     code?: string|null,
     *     fields?: array<int, array{field: string, location: string, code: string, message: string}>|null,
     *     meta?: array<string, mixed>|null,
     *     payload?: mixed
     * } $options
     */
    public function __construct(string $message, array $options)
    {
        parent::__construct($message, payload: $options['payload'] ?? null);

        $this->status = $options['status'];
        $this->apiCode = $options['code'] ?? null;
        $this->fields = $options['fields'] ?? null;
        $this->meta = $options['meta'] ?? null;
    }

    public function getApiCode(): ?string
    {
        return $this->apiCode;
    }

    public function __get(string $name): mixed
    {
        if ($name === 'code') {
            return $this->apiCode;
        }

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0] ?? [];
        trigger_error(
            sprintf(
                'Undefined property: %s::$%s in %s on line %s',
                self::class,
                $name,
                $trace['file'] ?? 'unknown',
                $trace['line'] ?? 'unknown',
            ),
            E_USER_NOTICE,
        );

        return null;
    }

    public function __isset(string $name): bool
    {
        return $name === 'code' && $this->apiCode !== null;
    }
}

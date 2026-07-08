<?php

declare(strict_types=1);

namespace Invoq;

class InvoqSignatureVerificationError extends InvoqError
{
    public readonly string $signatureCode;

    public function __construct(string $code, string $message)
    {
        parent::__construct($message);

        $this->signatureCode = $code;
    }

    public function getSignatureCode(): string
    {
        return $this->signatureCode;
    }

    public function __get(string $name): mixed
    {
        if ($name === 'code') {
            return $this->signatureCode;
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
        return $name === 'code';
    }
}

<?php

declare(strict_types=1);

namespace Invoq;

use RuntimeException;
use Throwable;

class InvoqError extends RuntimeException
{
    public readonly mixed $payload;

    public function __construct(string $message, ?Throwable $previous = null, mixed $payload = null)
    {
        parent::__construct($message, 0, $previous);

        $this->payload = $payload;
    }
}

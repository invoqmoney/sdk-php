<?php

declare(strict_types=1);

require __DIR__ . '/../../src/InvoqError.php';
require __DIR__ . '/../../src/InvoqApiError.php';
require __DIR__ . '/../../src/Internal/Request.php';

$method = new ReflectionMethod(Invoq\Internal\Request::class, 'userAgent');

$userAgent = $method->invoke(null);

if ($userAgent !== 'invoq-php/unknown') {
    fwrite(STDERR, 'Expected invoq-php/unknown, got ' . var_export($userAgent, true) . ".\n");
    exit(1);
}

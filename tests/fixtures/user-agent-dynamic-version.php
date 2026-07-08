<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    if ($class !== 'Composer\\InstalledVersions') {
        return;
    }

    require __DIR__ . '/composer-installed-versions-stub.php';
});

require __DIR__ . '/../../vendor/autoload.php';

$method = new ReflectionMethod(Invoq\Internal\Request::class, 'userAgent');

$userAgent = $method->invoke(null);

if ($userAgent !== 'invoq-php/9.8.7-test') {
    fwrite(STDERR, 'Expected invoq-php/9.8.7-test, got ' . var_export($userAgent, true) . ".\n");
    exit(1);
}

Composer\InstalledVersions::$prettyVersion = 'v0.1.0';
$userAgent = $method->invoke(null);

if ($userAgent !== 'invoq-php/0.1.0') {
    fwrite(STDERR, 'Expected invoq-php/0.1.0, got ' . var_export($userAgent, true) . ".\n");
    exit(1);
}

Composer\InstalledVersions::$prettyVersion = '1.0.0+no-version-set';
$userAgent = $method->invoke(null);

if ($userAgent !== 'invoq-php/unknown') {
    fwrite(STDERR, 'Expected invoq-php/unknown, got ' . var_export($userAgent, true) . ".\n");
    exit(1);
}

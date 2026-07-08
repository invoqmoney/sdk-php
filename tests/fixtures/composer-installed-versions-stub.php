<?php

declare(strict_types=1);

namespace Composer;

final class InstalledVersions
{
    public static ?string $prettyVersion = '9.8.7-test';

    public static function getPrettyVersion(string $packageName): ?string
    {
        return $packageName === 'invoq/invoq-php' ? self::$prettyVersion : null;
    }
}

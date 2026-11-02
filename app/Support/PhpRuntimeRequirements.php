<?php

namespace App\Support;

class PhpRuntimeRequirements
{
    public const MINIMUM_VERSION = '8.3.0';

    public static function supports(string $version = PHP_VERSION): bool
    {
        return version_compare($version, self::MINIMUM_VERSION, '>=');
    }
}

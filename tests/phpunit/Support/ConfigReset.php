<?php

declare(strict_types=1);

namespace CodeceptionPhpunit\Support;

use Codeception\Configuration;

final class ConfigReset
{
    /** @var array<string, mixed> */
    private static array $snapshot = [];

    public static function capture(): void
    {
        self::$snapshot = StaticPropertySnapshot::capture(Configuration::class);
    }

    public static function restore(): void
    {
        if (self::$snapshot !== []) {
            StaticPropertySnapshot::restore(Configuration::class, self::$snapshot);
        }
    }
}

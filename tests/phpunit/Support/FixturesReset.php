<?php

declare(strict_types=1);

namespace CodeceptionPhpunit\Support;

use Codeception\Util\Fixtures;

final class FixturesReset
{
    /** @var array<string, mixed> */
    private static array $snapshot = [];

    public static function capture(): void
    {
        self::$snapshot = StaticPropertySnapshot::capture(Fixtures::class);
    }

    public static function restore(): void
    {
        if (self::$snapshot !== []) {
            StaticPropertySnapshot::restore(Fixtures::class, self::$snapshot);
        }
    }
}

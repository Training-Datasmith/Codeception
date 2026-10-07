<?php

declare(strict_types=1);

namespace CodeceptionPhpunit\Support;

final class EnvReset
{
    /** @var array<string, mixed> */
    private static array $serverSnapshot = [];

    /** @var array<string, mixed> */
    private static array $envSnapshot = [];

    /** @var string[] */
    private static array $putenvKeys = [];

    public static function capture(): void
    {
        self::$serverSnapshot = $_SERVER;
        self::$envSnapshot = $_ENV;
        self::$putenvKeys = [];
    }

    public static function restore(): void
    {
        $_SERVER = self::$serverSnapshot;
        $_ENV = self::$envSnapshot;
        foreach (self::$putenvKeys as $key) {
            putenv($key);
        }
        self::$putenvKeys = [];
    }

    public static function putenv(string $assignment): void
    {
        $name = explode('=', $assignment, 2)[0];
        self::$putenvKeys[] = $name;
        putenv($assignment);
    }
}

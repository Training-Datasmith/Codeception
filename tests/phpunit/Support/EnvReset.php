<?php

declare(strict_types=1);

namespace CodeceptionPhpunit\Support;

final class EnvReset
{
    /** @var array<string, mixed> */
    private static array $serverSnapshot = [];

    /** @var array<string, mixed> */
    private static array $envSnapshot = [];

    /**
     * Original getenv values for keys touched via putenv(); false means the key did not exist.
     *
     * @var array<string, string|false>
     */
    private static array $putenvOriginal = [];

    public static function capture(): void
    {
        self::$serverSnapshot = $_SERVER;
        self::$envSnapshot = $_ENV;
        self::$putenvOriginal = [];
    }

    public static function restore(): void
    {
        $_SERVER = self::$serverSnapshot;
        $_ENV = self::$envSnapshot;
        foreach (self::$putenvOriginal as $name => $original) {
            if ($original === false) {
                putenv($name);
                unset($_ENV[$name], $_SERVER[$name]);
            } else {
                putenv($name . '=' . $original);
            }
        }
        self::$putenvOriginal = [];
    }

    public static function putenv(string $assignment): void
    {
        $name = explode('=', $assignment, 2)[0];
        if (!array_key_exists($name, self::$putenvOriginal)) {
            $value = getenv($name);
            self::$putenvOriginal[$name] = $value !== false ? $value : false;
        }
        putenv($assignment);
    }
}

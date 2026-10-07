<?php

declare(strict_types=1);

namespace CodeceptionPhpunit\Support;

use Codeception\Util\Autoload;
use ReflectionProperty;

final class AutoloadReset
{
    /** @var array<string, array<int, string>>|null */
    private static ?array $mapSnapshot = null;

    public static function capture(): void
    {
        $property = new ReflectionProperty(Autoload::class, 'map');
        $property->setAccessible(true);
        self::$mapSnapshot = $property->getValue();
    }

    public static function restore(): void
    {
        if (self::$mapSnapshot === null) {
            return;
        }
        $property = new ReflectionProperty(Autoload::class, 'map');
        $property->setAccessible(true);
        $property->setValue(null, self::$mapSnapshot);
    }
}

<?php

declare(strict_types=1);

namespace CodeceptionPhpunit\Support;

use ReflectionClass;
use ReflectionProperty;

final class StaticPropertySnapshot
{
    /**
     * @return array<string, mixed>
     */
    public static function capture(string $className): array
    {
        $reflection = new ReflectionClass($className);
        $snapshot = [];
        foreach ($reflection->getProperties(ReflectionProperty::IS_STATIC) as $property) {
            if (!$property->isInitialized()) {
                continue;
            }
            $property->setAccessible(true);
            $snapshot[$property->getName()] = $property->getValue();
        }

        return $snapshot;
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    public static function restore(string $className, array $snapshot): void
    {
        $reflection = new ReflectionClass($className);
        foreach ($snapshot as $name => $value) {
            $property = $reflection->getProperty($name);
            $property->setAccessible(true);
            $property->setValue(null, $value);
        }
    }
}

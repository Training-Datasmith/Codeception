<?php

declare (strict_types=1);
namespace Codeception\Util;

use function array_keys;
use function array_map;
use function json_encode;
use function method_exists;
use function range;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use function var_export;
class Reflection_Helper
{
    public static function read_private_property(object $object, string $property, ?string $class = null): mixed
    {
        $ref = new ReflectionProperty($class ?? $object, $property);
        return $ref->get_value($object);
    }
    public static function set_private_property(object $object, string $property, mixed $value, ?string $class = null): void
    {
        $ref = new ReflectionProperty($class ?? $object, $property);
        $ref->set_value($object, $value);
    }
    public static function invoke_private_method(?object $object, string $method, array $args = [], ?string $class = null): mixed
    {
        $ref = new ReflectionMethod($class ?? $object, $method);
        return $ref->invoke_args($object, $args);
    }
    /**
     * Returns class name without namespace (does not use reflection actually).
     */
    public static function get_class_short_name(object $object): string
    {
        $full = $object::class;
        $pos = strrpos($full, '\\');
        return $pos === false ? $full : substr($full, $pos + 1);
    }
    public static function get_class_from_parameter(ReflectionParameter $parameter): ?string
    {
        $type = $parameter->get_type();
        if (!$type instanceof ReflectionNamedType || $type->is_builtin()) {
            return null;
        }
        return match ($type->get_name()) {
            'self' => $parameter->get_declaring_class()->get_name(),
            'parent' => $parameter->get_declaring_class()->get_parent_class()->get_name(),
            default => $type->get_name(),
        };
    }
    /**
     * Infer default parameter from the reflection object and format it as PHP (code) string
     */
    public static function get_default_value(ReflectionParameter $parameter): string
    {
        if ($parameter->is_default_value_available()) {
            if (method_exists($parameter, 'isDefaultValueConstant') && $parameter->is_default_value_constant()) {
                $name = (string) $parameter->get_default_value_constant_name();
                if (str_contains($name, '::')) {
                    [$class, $const] = explode('::', $name, 2);
                    if (in_array($class, ['self', 'static'], true)) {
                        $name = '\\' . $parameter->get_declaring_class()->get_name() . '::' . $const;
                    } elseif (!str_starts_with($class, '\\')) {
                        $name = '\\' . $name;
                    }
                }
                return $name;
            }
            return self::php_encode_value($parameter->get_default_value());
        }
        $type = $parameter->get_type();
        if (!$type || $type->allows_null() || !$type instanceof ReflectionNamedType || !$type->is_builtin()) {
            return 'null';
        }
        return match ($type->get_name()) {
            'string' => "''",
            'array' => '[]',
            'bool' => 'false',
            'int', 'float' => '0',
            default => 'null',
        };
    }
    public static function php_encode_value(mixed $value): string
    {
        return is_array($value) ? self::php_encode_array($value) : (is_string($value) ? json_encode($value, JSON_THROW_ON_ERROR) : var_export($value, true));
    }
    /**
     * Recursively PHP encode an array
     */
    public static function php_encode_array(array $array): string
    {
        $is_sequential = array_keys($array) === range(0, count($array) - 1);
        if ($is_sequential) {
            return '[' . implode(', ', array_map(self::php_encode_value(...), $array)) . ']';
        }
        $encoded = array_map(static fn(int|string $k): string => self::php_encode_value($k) . ' => ' . self::php_encode_value($array[$k]), array_keys($array));
        return '[' . implode(', ', $encoded) . ']';
    }
}
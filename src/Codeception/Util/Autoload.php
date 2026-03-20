<?php

declare (strict_types=1);
namespace Codeception\Util;

use function array_unshift;
use function file_exists;
use function rtrim;
use function spl_autoload_register;
use function str_contains;
use function str_replace;
use function strrchr;
use function strrpos;
use function substr;
use function trim;
/**
 * Autoloader, which is fully compatible with PSR-4,
 * and can be used to autoload your `Helper`, `Page`, and `Step` classes.
 */
class Autoload
{
    protected static bool $registered = false;
    /**
     * An associative array where the key is a namespace prefix and the value
     * is an array of base directories for classes in that namespace.
     */
    protected static array $map = [];
    private function __construct()
    {
    }
    /**
     * Adds a base directory for a namespace prefix.
     *
     * Example:
     *
     * ```php
     * <?php
     * // app\Codeception\UserHelper will be loaded from '/path/to/helpers/UserHelper.php'
     * Autoload::addNamespace('app\Codeception', '/path/to/helpers');
     *
     * // LoginPage will be loaded from '/path/to/pageobjects/LoginPage.php'
     * Autoload::addNamespace('', '/path/to/pageobjects');
     *
     * Autoload::addNamespace('app\Codeception', '/path/to/controllers');
     * ```
     *
     * @param string $prefix The namespace prefix.
     * @param string $baseDir A base directory for class files in the namespace.
     * @param bool $prepend If true, prepend the base directory to the stack instead of appending it;
     *                      this causes it to be searched first rather than last.
     */
    public static function add_namespace(string $prefix, string $base_dir, bool $prepend = false): void
    {
        if (!self::$registered) {
            spl_autoload_register(self::load(...));
            self::$registered = true;
        }
        $prefix = trim($prefix, '\\') . '\\';
        $base_dir = rtrim($base_dir, '/\\') . '/';
        self::$map[$prefix] ??= [];
        if ($prepend) {
            array_unshift(self::$map[$prefix], $base_dir);
        } else {
            self::$map[$prefix][] = $base_dir;
        }
    }
    public static function load(string $class): string|false
    {
        $prefix = $class;
        while (false !== $pos = strrpos($prefix, '\\')) {
            $prefix = substr($class, 0, $pos + 1);
            $relative_class = substr($class, $pos + 1);
            if ($file = self::load_mapped_file($prefix, $relative_class)) {
                return $file;
            }
            $prefix = rtrim($prefix, '\\');
        }
        if (isset(self::$map['\\']) && ($class[0] ?? '') !== '\\') {
            return self::load('\\' . $class);
        }
        if (str_contains($class, '\\')) {
            $relative_class = substr(strrchr($class, '\\'), 1);
            if ($file = self::load_mapped_file('\\', $relative_class)) {
                return $file;
            }
        }
        return false;
    }
    /**
     * Load the mapped file for a namespace prefix and relative class.
     *
     * @param string $prefix The namespace prefix.
     * @param string $relativeClass The relative class name.
     * @return string|false Boolean false if no mapped file can be loaded, or the name of the mapped file that was loaded.
     */
    protected static function load_mapped_file(string $prefix, string $relative_class): string|false
    {
        if (!isset(self::$map[$prefix])) {
            return false;
        }
        foreach (self::$map[$prefix] as $base_dir) {
            $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
            if (static::require_file($file)) {
                return $file;
            }
        }
        return false;
    }
    protected static function require_file($file): bool
    {
        if (file_exists($file)) {
            require_once $file;
            return true;
        }
        return false;
    }
}
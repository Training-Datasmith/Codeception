<?php

declare (strict_types=1);
namespace Codeception\Util;

use Throwable;
class Stack_Trace_Filter
{
    protected static array $filtered_classes_pattern = ['Symfony\Component\Console', 'Codeception\Command\\', 'Codeception\TestCase\\'];
    public static function get_filtered_stack_trace(Throwable $e, bool $as_string = true, bool $filter = true): array|string
    {
        $trace = ($e->get_previous() ?? $e)->get_trace();
        if (!self::frame_exists($trace, $e->get_file(), $e->get_line())) {
            array_unshift($trace, ['file' => $e->get_file(), 'line' => $e->get_line()]);
        }
        if ($filter) {
            $trace = array_values(array_filter($trace, static fn(array $step): bool => !self::class_is_filtered($step) && !self::file_is_filtered($step)));
        }
        if (!$as_string) {
            return $trace;
        }
        return implode("\n", array_map(static fn(array $step): string => $step['file'] . ':' . $step['line'], array_filter($trace, static fn(array $step): bool => isset($step['file'], $step['line']))));
    }
    protected static function class_is_filtered(array $step): bool
    {
        if (!isset($step['class'])) {
            return false;
        }
        foreach (self::$filtered_classes_pattern as $pattern) {
            if (str_starts_with($step['class'], (string) $pattern)) {
                return true;
            }
        }
        return false;
    }
    /** @param string[] $step */
    protected static function file_is_filtered(array $step): bool
    {
        if (!isset($step['file'])) {
            return false;
        }
        $file = $step['file'];
        $vendor = 'vendor' . DIRECTORY_SEPARATOR;
        if (str_contains($file, 'codecept.phar/') || str_contains($file, $vendor . 'phpunit') || str_contains($file, $vendor . 'codeception')) {
            return true;
        }
        $module_path = 'src' . DIRECTORY_SEPARATOR . 'Codeception' . DIRECTORY_SEPARATOR . 'Module';
        if (str_contains($file, $module_path)) {
            return false;
        }
        return str_contains($file, 'src' . DIRECTORY_SEPARATOR . 'Codeception' . DIRECTORY_SEPARATOR);
    }
    private static function frame_exists(array $trace, string $file, int $line): bool
    {
        foreach ($trace as $frame) {
            if (($frame['file'] ?? null) === $file && ($frame['line'] ?? null) === $line) {
                return true;
            }
        }
        return false;
    }
}
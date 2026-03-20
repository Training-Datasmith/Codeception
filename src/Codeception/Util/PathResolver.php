<?php

declare (strict_types=1);
namespace Codeception\Util;

use function array_fill;
use function array_filter;
use function array_merge;
use function array_shift;
use function count;
use function explode;
use function implode;
use function preg_match;
use function strlen;
use function substr;
class Path_Resolver
{
    /**
     * Returns path to a given directory relative to $projDir.
     */
    public static function get_relative_dir(string $path, string $proj_dir, string $dir_sep = DIRECTORY_SEPARATOR): string
    {
        $proj_dir = rtrim($proj_dir, $dir_sep) . $dir_sep;
        $proj_len = strlen($proj_dir);
        if (self::fs_case_str_cmp(substr($path, 0, $proj_len), $proj_dir, $dir_sep) === 0) {
            return substr($path, $proj_len);
        }
        $path_pref = self::get_path_absoluteness_prefix($path, $dir_sep);
        $proj_pref = self::get_path_absoluteness_prefix($proj_dir, $dir_sep);
        if (self::fs_case_str_cmp($path_pref['wholePrefix'], $proj_pref['wholePrefix'], $dir_sep) !== 0) {
            if ($path_pref['devicePrefix'] !== '' && self::fs_case_str_cmp($path_pref['devicePrefix'], $proj_pref['devicePrefix'], $dir_sep) === 0) {
                return substr($path, strlen($path_pref['devicePrefix']));
            }
            return $path;
        }
        $base_len = strlen($path_pref['wholePrefix']);
        $parts_path = array_values(array_filter(explode($dir_sep, substr($path, $base_len))));
        $parts_proj = array_values(array_filter(explode($dir_sep, substr($proj_dir, strlen($proj_pref['wholePrefix'])))));
        while ($parts_path && $parts_proj && self::fs_case_str_cmp($parts_path[0], $parts_proj[0], $dir_sep) === 0) {
            array_shift($parts_path);
            array_shift($parts_proj);
        }
        if ($parts_proj !== []) {
            $parts_path = array_merge(array_fill(0, count($parts_proj), '..'), $parts_path);
        }
        $trailing_sep = substr($path, -1) === $dir_sep ? $dir_sep : '';
        return implode($dir_sep, $parts_path) . $trailing_sep;
    }
    /**
     * FileSystem Case String Comparison
     * Compare two strings with the filesystem's case-sensitiveness
     *
     * @return int -1 / 0 / 1 for < / = / > respectively
     */
    private static function fs_case_str_cmp(string $str1, string $str2, string $dir_sep = DIRECTORY_SEPARATOR): int
    {
        $cmp_fn = self::is_windows_filesystem($dir_sep) ? 'strcasecmp' : 'strcmp';
        return $cmp_fn($str1, $str2);
    }
    /**
     * What part of this path (leftmost 0-3 characters) what
     * it is absolute relative to:
     *
     * On Unix:
     *     This is simply '/' for an absolute path or
     *     '' for a relative path
     *
     * On Windows this is more complicated:
     *     If the first two characters are a letter followed
     *         by a ':', this indicates that the path is
     *         on a specific device.
     *     With or without a device specified, a path MAY
     *         start with a '\\' to indicate an absolute path
     *         on the device or '' to indicate a path relative
     *         to the device's CWD
     *
     * @return array<string, string>
     */
    private static function get_path_absoluteness_prefix(string $path, string $dir_sep = DIRECTORY_SEPARATOR): array
    {
        $is_windows = self::is_windows_filesystem($dir_sep);
        if ($is_windows && preg_match('/^[A-Za-z]:/', $path, $m)) {
            $dev = $m[0];
            $has_dir_sep = substr($path, strlen($dev), 1) === $dir_sep ? $dir_sep : '';
            return ['wholePrefix' => $dev . $has_dir_sep, 'devicePrefix' => $dev];
        }
        $whole_prefix = $path !== '' && $path[0] === $dir_sep ? $dir_sep : '';
        return ['wholePrefix' => $whole_prefix, 'devicePrefix' => ''];
    }
    private static function is_windows_filesystem(string $dir_sep = DIRECTORY_SEPARATOR): bool
    {
        return $dir_sep === '\\';
    }
    public static function is_path_absolute(string $path): bool
    {
        if (DIRECTORY_SEPARATOR === '/') {
            return $path !== '' && $path[0] === DIRECTORY_SEPARATOR;
        }
        return preg_match('#^[A-Z]:(?![^/\\\\])#i', $path) === 1;
    }
}
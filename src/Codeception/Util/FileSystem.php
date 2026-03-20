<?php

declare (strict_types=1);
namespace Codeception\Util;

use Filesystem_Iterator;
use Recursive_Directory_Iterator;
use Recursive_Iterator_Iterator;
class File_System
{
    public static function do_empty_dir(string $path): void
    {
        self::clear_dir($path, ['.gitignore', '.gitkeep']);
    }
    public static function delete_dir(string $dir): bool
    {
        if (!file_exists($dir)) {
            return true;
        }
        if (!is_dir($dir) || is_link($dir)) {
            return @unlink($dir);
        }
        if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
            $win_path = str_replace('/', '\\', $dir);
            exec(sprintf('rd /s /q "%s"', $win_path));
            return true;
        }
        self::clear_dir($dir);
        return @rmdir($dir);
    }
    public static function copy_dir(string $src, string $dst): void
    {
        if (!is_dir($src)) {
            return;
        }
        $src = rtrim($src, DIRECTORY_SEPARATOR);
        @mkdir($dst, 0777, true);
        $base_len = strlen($src) + 1;
        foreach (self::create_iterator($src, Recursive_Iterator_Iterator::SELF_FIRST) as $item) {
            $target = $dst . DIRECTORY_SEPARATOR . substr((string) $item->get_pathname(), $base_len);
            if ($item->is_dir()) {
                @mkdir($target, 0777, true);
            } else {
                copy($item->get_pathname(), $target);
            }
        }
    }
    /**
     * @param string[] $preserve
     */
    private static function clear_dir(string $path, array $preserve = []): void
    {
        foreach (self::create_iterator($path, Recursive_Iterator_Iterator::CHILD_FIRST) as $item) {
            if (in_array($item->get_filename(), $preserve, true)) {
                continue;
            }
            $item->is_dir() ? @rmdir($item->get_pathname()) : @unlink($item->get_pathname());
        }
    }
    private static function create_iterator(string $path, int $mode): Recursive_Iterator_Iterator
    {
        return new Recursive_Iterator_Iterator(new Recursive_Directory_Iterator($path, Filesystem_Iterator::SKIP_DOTS), $mode);
    }
}
<?php

declare(strict_types=1);

namespace Codeception\Util;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class FileSystem
{
    public static function doEmptyDir(string $path): void
    {
        self::clearDir($path, ['.gitignore', '.gitkeep']);
    }

    public static function deleteDir(string $dir): bool
    {
        if (!file_exists($dir)) {
            return true;
        }

        if (!is_dir($dir) || is_link($dir)) {
            return @unlink($dir);
        }

        if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
            $winPath = str_replace('/', '\\', $dir);
            exec(sprintf('rd /s /q "%s"', $winPath));
            return true;
        }

        self::clearDir($dir);
        return @rmdir($dir);
    }

    public static function copyDir(string $src, string $dst): void
    {
        if (!is_dir($src)) {
            return;
        }

        $src     = rtrim($src, DIRECTORY_SEPARATOR);
        @mkdir($dst, 0777, true);
        $baseLen = strlen($src) + 1;

        foreach (self::createIterator($src, RecursiveIteratorIterator::SELF_FIRST) as $item) {
            $target = $dst . DIRECTORY_SEPARATOR . substr((string) $item->getPathname(), $baseLen);
            if ($item->isDir()) {
                @mkdir($target, 0777, true);
            } else {
                copy($item->getPathname(), $target);
            }
        }
    }

    /**
     * @param string[] $preserve
     */
    private static function clearDir(string $path, array $preserve = []): void
    {
        foreach (self::createIterator($path, RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            if (in_array($item->getFilename(), $preserve, true)) {
                continue;
            }

            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
    }

    private static function createIterator(string $path, int $mode): RecursiveIteratorIterator
    {
        return new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            $mode
        );
    }
}

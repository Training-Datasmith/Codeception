<?php

declare (strict_types=1);
namespace Codeception\Command\Shared;

use Codeception\Util\Shared\Namespaces;
use function file_exists;
use function file_put_contents;
use function mkdir;
use function pathinfo;
use function preg_replace;
use function rtrim;
use function str_replace;
use function strrev;
trait File_System_Trait
{
    use Namespaces;
    protected function create_directory_for(string $base_path, string $class_name = ''): string
    {
        $base_path = rtrim($base_path, DIRECTORY_SEPARATOR);
        if ($class_name) {
            $class_name = str_replace(['/', '\\'], [DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR], $class_name);
            $path = $base_path . DIRECTORY_SEPARATOR . $class_name;
            $base_path = pathinfo($path, PATHINFO_DIRNAME) . DIRECTORY_SEPARATOR;
        }
        if (!file_exists($base_path)) {
            // Second argument should be mode. Well, umask() doesn't seem to return any if not set. Config may fix this.
            mkdir($base_path, 0775, true);
            // Third parameter commands to create directories recursively
        }
        return $base_path;
    }
    protected function complete_suffix(string $filename, string $suffix): string
    {
        if (str_starts_with(strrev($filename), strrev($suffix))) {
            $filename .= '.php';
        }
        if (!str_starts_with(strrev($filename), strrev($suffix . '.php'))) {
            $filename .= $suffix . '.php';
        }
        if (!str_starts_with(strrev($filename), strrev('.php'))) {
            $filename .= '.php';
        }
        return $filename;
    }
    protected function remove_suffix(string $classname, string $suffix): string
    {
        $classname = preg_replace('#\.php$#', '', $classname);
        return preg_replace("#{$suffix}\$#", '', (string) $classname);
    }
    protected function create_file(string $filename, string $contents, bool $force = false, int $flags = 0): bool
    {
        if (file_exists($filename) && !$force) {
            return false;
        }
        file_put_contents($filename, $contents, $flags);
        return true;
    }
}
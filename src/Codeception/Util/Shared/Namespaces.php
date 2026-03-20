<?php

declare (strict_types=1);
namespace Codeception\Util\Shared;

use function array_filter;
use function array_pop;
use function explode;
use function implode;
use function ltrim;
use function str_replace;
trait Namespaces
{
    /**
     * @return string[]
     */
    protected function break_parts(string $class): array
    {
        $class = str_replace('/', '\\', ltrim($class, './\\'));
        return explode('\\', $class);
    }
    protected function get_short_class_name(string $class): string
    {
        $namespaces = $this->break_parts($class);
        return array_pop($namespaces);
    }
    protected function get_namespace_string(string $class): string
    {
        return implode('\\', $this->get_namespaces($class));
    }
    protected function get_namespace_header(string $class): string
    {
        $str = $this->get_namespace_string($class);
        return $str ? "\nnamespace {$str};\n" : '';
    }
    protected function get_namespaces(string $class): array
    {
        $namespaces = $this->break_parts($class);
        array_pop($namespaces);
        return array_filter($namespaces, strlen(...));
    }
}
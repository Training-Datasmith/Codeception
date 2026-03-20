<?php

declare (strict_types=1);
namespace Codeception\Lib\Generator\Shared;

trait Classname
{
    protected function remove_suffix(string $classname, string $suffix): string
    {
        $classname = preg_replace('#\.php$#', '', $classname);
        return preg_replace("#{$suffix}\$#", '', (string) $classname);
    }
    protected function support_namespace(): string
    {
        if (!isset($this->settings)) {
            return '\\';
        }
        $namespace = '';
        if ($this->settings['namespace']) {
            $namespace .= '\\' . $this->settings['namespace'];
        }
        if (isset($this->settings['support_namespace'])) {
            $namespace .= '\\' . $this->settings['support_namespace'];
        }
        return trim($namespace, '\\') . '\\';
    }
}
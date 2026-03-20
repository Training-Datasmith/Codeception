<?php

declare (strict_types=1);
namespace Codeception\Step;

use Codeception\Lib\Module_Container;
use Codeception\Step as CodeceptionStep;
use function mb_strcut;
class Comment extends Codeception_Step
{
    public function __toString(): string
    {
        return $this->get_action();
    }
    public function to_string(int $max_length): string
    {
        return mb_strcut((string) $this, 0, $max_length, 'utf-8');
    }
    public function get_html(string $highlight_color = '#732E81'): string
    {
        return '<strong>' . $this->get_action() . '</strong>';
    }
    public function get_php_code(int $max_length): string
    {
        return '// ' . $this->get_action();
    }
    public function run(?Module_Container $container = null): void
    {
        // no-op
    }
    public function get_prefix(): string
    {
        return '';
    }
}
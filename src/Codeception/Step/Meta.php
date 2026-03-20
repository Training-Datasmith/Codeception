<?php

declare (strict_types=1);
namespace Codeception\Step;

use function array_pop;
use Codeception\Lib\Module_Container;
use Codeception\Step as CodeceptionStep;
use function end;
use function is_string;
use function str_contains;
use function str_replace;
class Meta extends Codeception_Step
{
    public function run(?Module_Container $container = null): void
    {
    }
    public function set_trace_info(string $file, int $line): void
    {
        $this->file = $file;
        $this->line = $line;
    }
    public function set_prefix(string $actor): void
    {
        $this->prefix = $actor;
    }
    public function get_arguments_as_string(int $max_length = self::DEFAULT_MAX_LENGTH): string
    {
        $backup = $this->arguments;
        $last_arg = end($this->arguments);
        $last_arg_str = '';
        if (is_string($last_arg) && str_contains($last_arg, "\n")) {
            $last_arg_str = "\r\n   " . str_replace("\n", "\n   ", $last_arg);
            array_pop($this->arguments);
        }
        $result = parent::get_arguments_as_string($max_length) . $last_arg_str;
        $this->arguments = $backup;
        return $result;
    }
    public function set_failed(bool $failed): void
    {
        $this->failed = $failed;
    }
}
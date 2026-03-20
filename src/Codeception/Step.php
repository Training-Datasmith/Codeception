<?php

declare (strict_types=1);
namespace Codeception;

use Closure;
use Codeception\Lib\Module_Container;
use Codeception\Step\Argument\Formatted_Output;
use Codeception\Step\Meta as MetaStep;
use Codeception\Util\Locator;
use Exception;
use Php_Unit\Framework\Constraint\Constraint;
use Php_Unit\Framework\Mock_Object\Mock_Object;
use ReflectionClass;
use RuntimeException;
use Stringable;
abstract class Step implements Stringable
{
    /**
     * @var int
     */
    public const DEFAULT_MAX_LENGTH = 200;
    /**
     * @var int
     */
    public const STACK_POSITION = 3;
    public bool $executed = false;
    protected bool $failed = false;
    protected bool $is_try = false;
    protected string|int|null $line = null;
    protected ?string $file = null;
    protected string $prefix = 'I';
    protected ?Meta_Step $meta_step = null;
    /** @param string[] $arguments */
    public function __construct(protected string $action, protected array $arguments = [])
    {
    }
    public function save_trace(): void
    {
        $stack = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);
        if (count($stack) <= self::STACK_POSITION) {
            return;
        }
        $trace = $stack[self::STACK_POSITION - 1];
        if (!isset($trace['file'])) {
            return;
        }
        $this->file = $trace['file'];
        $this->line = $trace['line'];
        $this->add_meta_step($trace, $stack);
    }
    private function is_test_file(string $file): int|false
    {
        return preg_match('#[^\\' . DIRECTORY_SEPARATOR . '](Cest|Cept|Test).php$#', $file);
    }
    public function get_name(): string
    {
        $parts = explode('\\', self::class);
        return end($parts);
    }
    public function get_action(): string
    {
        return $this->action;
    }
    public function get_file_path(): ?string
    {
        return $this->file ? codecept_relative_path($this->file) : null;
    }
    public function get_line_number(): ?int
    {
        return $this->line ?: null;
    }
    public function has_failed(): bool
    {
        return $this->failed;
    }
    public function get_arguments(): array
    {
        return $this->arguments;
    }
    public function get_arguments_as_string(int $max_length = self::DEFAULT_MAX_LENGTH): string
    {
        $arguments = $this->arguments;
        $argument_count = count($arguments);
        $total_length = $argument_count - 1;
        foreach ($arguments as $key => $argument) {
            $stringified = $this->stringify_argument($argument);
            $arguments[$key] = $stringified;
            $total_length += mb_strlen($stringified, 'utf-8');
        }
        if ($total_length > $max_length && $max_length > 0) {
            uasort($arguments, fn($a, $b): int => mb_strlen($a, 'utf-8') <=> mb_strlen($b, 'utf-8'));
            $allowed_length = floor(($max_length - $argument_count + 1) / $argument_count);
            $length_remaining = $max_length;
            $arguments_remaining = $argument_count;
            foreach ($arguments as $key => $arg) {
                --$arguments_remaining;
                if (mb_strlen($arg, 'utf-8') > $allowed_length) {
                    $arguments[$key] = mb_substr($arg, 0, (int) $allowed_length - 4, 'utf-8') . '...' . mb_substr($arg, -1, 1, 'utf-8');
                    $length_remaining -= $allowed_length + 1;
                } else {
                    $length_remaining -= mb_strlen($arg, 'utf-8') + 1;
                    if ($arguments_remaining > 0) {
                        $allowed_length = floor(($length_remaining - $arguments_remaining + 1) / $arguments_remaining);
                    }
                }
            }
            ksort($arguments);
        }
        return implode(',', $arguments);
    }
    protected function stringify_argument(mixed $argument): string
    {
        if (is_string($argument)) {
            return '"' . strtr($argument, ["\n" => '\n', "\r" => '\r', "\t" => ' ']) . '"';
        }
        if (is_resource($argument)) {
            $argument = (string) $argument;
        } elseif (is_array($argument)) {
            foreach ($argument as $key => $value) {
                if (is_object($value)) {
                    $argument[$key] = $this->get_class_name($value);
                }
            }
        } elseif (is_object($argument)) {
            if ($argument instanceof Formatted_Output) {
                $argument = $argument->get_output();
            } elseif (method_exists($argument, '__toString')) {
                $argument = (string) $argument;
            } elseif ($argument::class === 'Facebook\WebDriver\WebDriverBy') {
                $argument = Locator::human_readable_string($argument);
            } elseif ($argument instanceof Constraint) {
                $argument = $argument->to_string();
            } else {
                $argument = $this->get_class_name($argument);
            }
        }
        $arg_str = json_encode($argument, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        return str_replace('"', '"', $arg_str);
    }
    protected function get_class_name(object $argument): string
    {
        if ($argument instanceof Closure) {
            return Closure::class;
        }
        if ($argument instanceof Mock_Object) {
            $parent = get_parent_class($argument);
            if ($parent) {
                return $this->format_class_name($parent);
            }
            foreach ((new ReflectionClass($argument))->get_interface_names() as $interface) {
                if (!str_starts_with($interface, 'PHPUnit\\') && !str_starts_with($interface, 'Codeception\\')) {
                    return $this->format_class_name($interface);
                }
            }
        }
        return $this->format_class_name($argument::class);
    }
    protected function format_class_name(string $classname): string
    {
        return trim($classname, '\\');
    }
    public function get_php_code(int $max_length): string
    {
        $base = "\${$this->prefix}->" . $this->get_action() . '(';
        $remaining = $max_length - mb_strlen($base, 'utf-8') - 1;
        return $base . $this->get_humanized_arguments($remaining) . ')';
    }
    public function get_meta_step(): ?Meta_Step
    {
        return $this->meta_step;
    }
    public function __toString(): string
    {
        return $this->humanize($this->get_action()) . ' ' . $this->get_humanized_arguments();
    }
    public function to_string(int $max_length): string
    {
        $action = $this->humanize($this->get_action());
        $remaining = $max_length - mb_strlen($action, 'utf-8') - 1;
        return $action . ' ' . $this->get_humanized_arguments($remaining);
    }
    public function get_html(string $highlight_color = '#732E81'): string
    {
        if ($this->arguments === []) {
            return sprintf('%s %s', ucfirst($this->prefix), $this->humanize($this->get_action()));
        }
        return sprintf('%s %s <span style="color: %s">%s</span>', ucfirst($this->prefix), htmlspecialchars($this->humanize($this->get_action()), ENT_QUOTES | ENT_SUBSTITUTE), $highlight_color, htmlspecialchars($this->get_humanized_arguments(0), ENT_QUOTES | ENT_SUBSTITUTE));
    }
    public function get_humanized_action_without_arguments(): string
    {
        return $this->humanize($this->get_action());
    }
    public function get_humanized_arguments(int $max_length = self::DEFAULT_MAX_LENGTH): string
    {
        return $this->get_arguments_as_string($max_length);
    }
    protected function clean(string $text): string
    {
        return str_replace('\/', '', $text);
    }
    protected function humanize(string $text): string
    {
        $text = preg_replace('#([A-Z]+)([A-Z][a-z])#', '\1 \2', $text);
        $text = preg_replace('#([a-z\d])([A-Z])#', '\1 \2', (string) $text);
        $text = preg_replace('#\bdont\b#', "don't", (string) $text);
        return mb_strtolower((string) $text, 'UTF-8');
    }
    /**
     * @return mixed
     */
    public function run(?Module_Container $container = null)
    {
        $this->executed = true;
        if (!$container instanceof Module_Container) {
            return null;
        }
        $module = $container->module_for_action($this->action);
        if (!is_callable([$module, $this->action])) {
            throw new RuntimeException("Action '{$this->action}' can't be called");
        }
        try {
            return $module->{$this->action}(...$this->arguments);
        } catch (Exception $e) {
            if ($this->is_try) {
                throw $e;
            }
            $this->failed = true;
            $this->meta_step?->set_failed(true);
            throw $e;
        }
    }
    /**
     * If steps are combined into one method they can be reproduced as meta-step.
     * We are using stack trace to analyze if steps were called from test, if not - they were called from meta-step.
     */
    protected function add_meta_step(array $step, array $stack): void
    {
        if ($this->is_test_file($this->file) || $step['class'] === Scenario::class) {
            return;
        }
        for ($i = count($stack) - self::STACK_POSITION - 1; isset($stack[$i]); --$i) {
            $step = $stack[$i];
            if (!isset($step['file'], $step['function'], $step['class'])) {
                continue;
            }
            if (!$this->is_test_file($step['file'])) {
                continue;
            }
            $this->meta_step = new Step\Meta($step['function'], array_map(fn($v) => $v, array_values($step['args'])));
            $this->meta_step->set_trace_info($step['file'], $step['line']);
            if (!in_array(Actor::class, class_parents($step['class']))) {
                if (isset($step['object'])) {
                    $this->meta_step->set_prefix($step['object']::class . ':');
                } else {
                    $this->meta_step->set_prefix($step['class'] . ':');
                }
            }
            return;
        }
    }
    public function set_meta_step(?Meta_Step $meta_step): void
    {
        $this->meta_step = $meta_step;
    }
    public function get_prefix(): string
    {
        return $this->prefix . ' ';
    }
}
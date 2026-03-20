<?php

declare (strict_types=1);
namespace Codeception\Util;

use function array_key_exists;
use function explode;
use function is_array;
use function preg_quote;
use function preg_replace_callback;
use function sprintf;
use function strval;
/**
 * Basic template engine used for generating initial Cept/Cest/Test files.
 */
class Template
{
    private array $vars = [];
    private readonly string $regex;
    public function __construct(private readonly string $template, private readonly string $placeholder_start = '{{', private readonly string $placeholder_end = '}}', private readonly ?string $encoder_function = null)
    {
        $this->regex = sprintf('~%s([\w\.]+)%s~', preg_quote($this->placeholder_start, '~'), preg_quote($this->placeholder_end, '~'));
    }
    /**
     * Replaces {{var}} string with provided value
     */
    public function place(string $var, $val): self
    {
        $this->vars[$var] = $val;
        return $this;
    }
    /**
     * Sets all template vars
     */
    public function set_vars(array $vars): void
    {
        $this->vars = $vars;
    }
    public function get_var(string $name)
    {
        return $this->vars[$name] ?? null;
    }
    /**
     * Fills up template string with placed variables.
     */
    public function produce(): string
    {
        return preg_replace_callback($this->regex, function (array $match): string {
            $placeholder = $match[1];
            $value = $this->vars;
            foreach (explode('.', trim($placeholder, '\'"')) as $segment) {
                if (is_array($value) && array_key_exists($segment, $value)) {
                    $value = $value[$segment];
                } else {
                    return $match[0];
                }
            }
            $value = $this->encoder_function !== null ? ($this->encoder_function)($value) : $value;
            return is_string($value) ? $value : strval($value);
        }, $this->template);
    }
}
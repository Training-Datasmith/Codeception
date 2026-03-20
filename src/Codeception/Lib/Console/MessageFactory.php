<?php

declare (strict_types=1);
namespace Codeception\Lib\Console;

use Sebastian_Bergmann\Comparator\Comparison_Failure;
class Message_Factory
{
    protected Diff_Factory $diff_factory;
    protected Colorizer $colorizer;
    public function __construct(private readonly Output $output)
    {
        $this->diff_factory = new Diff_Factory();
        $this->colorizer = new Colorizer();
    }
    public function message(string $text = ''): Message
    {
        return new Message($text, $this->output);
    }
    public function prepare_comparison_failure_message(Comparison_Failure $failure): string
    {
        $diff = $this->diff_factory->create_diff($failure);
        if ($diff === '') {
            return '';
        }
        $diff = $this->colorizer->colorize($diff);
        return "\n<comment>- Expected</comment> | <info>+ Actual</info>\n{$diff}";
    }
}
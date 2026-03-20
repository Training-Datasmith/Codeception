<?php

declare (strict_types=1);
namespace Codeception\Lib\Console;

use Symfony\Component\Console\Formatter\Output_Formatter;
class Colorizer
{
    public function colorize(string $string = ''): string
    {
        $lines = explode("\n", $string);
        $colorized_message = '';
        foreach ($lines as $line) {
            $char = $line[0] ?? '';
            $line = Output_Formatter::escape(trim($line));
            switch ($char) {
                case '+':
                    $line = "<info>{$line}</info>";
                    break;
                case '-':
                    $line = "<comment>{$line}</comment>";
                    break;
            }
            $colorized_message .= $line . "\n";
        }
        return trim($colorized_message);
    }
}
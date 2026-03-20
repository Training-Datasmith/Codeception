<?php

declare (strict_types=1);
namespace Codeception\Exception;

use Exception;
class Test_Parse_Exception extends Exception
{
    public function __construct(string $file_name, ?string $errors = null, ?int $line = null)
    {
        $this->message = "Couldn't parse test '{$file_name}'";
        if ($line !== null) {
            $this->message .= " on line {$line}";
        }
        if ($errors) {
            $this->message .= PHP_EOL . $errors;
        }
    }
}
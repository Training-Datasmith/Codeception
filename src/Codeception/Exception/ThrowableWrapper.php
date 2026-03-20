<?php

declare (strict_types=1);
namespace Codeception\Exception;

use Throwable;
class Throwable_Wrapper extends Error
{
    public function __construct(Throwable $throwable)
    {
        parent::__construct($throwable::class . ': ' . $throwable->get_message(), $throwable->get_code(), $throwable->get_file(), $throwable->get_line());
    }
}
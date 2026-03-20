<?php

declare (strict_types=1);
namespace Codeception\Event;

use Codeception\Test\Test;
use Throwable;
class Fail_Event extends Test_Event
{
    public function __construct(Test $test, private readonly Throwable $fail, ?float $time)
    {
        parent::__construct($test, $time);
    }
    public function get_fail(): Throwable
    {
        return $this->fail;
    }
}
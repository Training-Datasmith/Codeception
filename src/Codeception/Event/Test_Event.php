<?php

declare (strict_types=1);
namespace Codeception\Event;

use Codeception\Test\Test;
use Symfony\Contracts\Event_Dispatcher\Event;
class Test_Event extends Event
{
    /**
     * @param float|null $time Time taken
     */
    public function __construct(protected Test $test, protected ?float $time = 0)
    {
    }
    public function get_time(): float
    {
        return $this->time;
    }
    public function get_test(): Test
    {
        return $this->test;
    }
}
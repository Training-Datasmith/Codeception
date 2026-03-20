<?php

declare (strict_types=1);
namespace Codeception\Event;

use Codeception\Step;
use Codeception\Test_Interface;
use Symfony\Contracts\Event_Dispatcher\Event;
class Step_Event extends Event
{
    public function __construct(protected Test_Interface $test, protected Step $step)
    {
    }
    public function get_step(): Step
    {
        return $this->step;
    }
    public function get_test(): Test_Interface
    {
        return $this->test;
    }
}
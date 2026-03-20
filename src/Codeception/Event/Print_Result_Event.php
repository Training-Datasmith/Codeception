<?php

declare (strict_types=1);
namespace Codeception\Event;

use Codeception\Result_Aggregator;
use Symfony\Contracts\Event_Dispatcher\Event;
class Print_Result_Event extends Event
{
    public function __construct(protected Result_Aggregator $result)
    {
    }
    public function get_result(): Result_Aggregator
    {
        return $this->result;
    }
}
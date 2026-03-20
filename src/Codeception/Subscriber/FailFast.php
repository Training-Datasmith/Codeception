<?php

declare (strict_types=1);
namespace Codeception\Subscriber;

use Codeception\Event\Test_Event;
use Codeception\Events;
use Codeception\Result_Aggregator;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
class Fail_Fast implements Event_Subscriber_Interface
{
    use Shared\Static_Events_Trait;
    /**
     * @var array<string, array<string|int>>
     */
    protected static array $events = [Events::TEST_FAIL => ['stopOnFail', 128], Events::TEST_ERROR => ['stopOnFail', 128]];
    private int $failure_count = 0;
    public function __construct(private int $stop_failure_count, private Result_Aggregator $result_aggregator)
    {
    }
    public function stop_on_fail(Test_Event $e): void
    {
        if (++$this->failure_count >= $this->stop_failure_count) {
            $this->result_aggregator->stop();
        }
    }
}
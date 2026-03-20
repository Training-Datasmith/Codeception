<?php

declare (strict_types=1);
namespace Codeception\Subscriber;

use function call_user_func;
use Codeception\Event\Suite_Event;
use Codeception\Events;
use Codeception\Test\Test;
use Codeception\Test\Test_Case_Wrapper;
use function is_callable;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
class Before_After_Test implements Event_Subscriber_Interface
{
    use Shared\Static_Events_Trait;
    /**
     * @var array<string, string|int[]|string[]>
     */
    protected static array $events = [Events::SUITE_BEFORE => 'beforeClass', Events::SUITE_AFTER => ['afterClass', 100]];
    public function before_class(Suite_Event $event): void
    {
        foreach ($event->get_suite()->get_tests() as $test) {
            $this->execute_methods($test, $test->get_metadata()->get_before_class_methods());
        }
    }
    public function after_class(Suite_Event $event): void
    {
        foreach ($event->get_suite()->get_tests() as $test) {
            $this->execute_methods($test, $test->get_metadata()->get_after_class_methods());
        }
    }
    private function execute_methods(Test $test, array $methods): void
    {
        if ($test instanceof Test_Case_Wrapper) {
            $test = $test->get_test_case();
        }
        foreach ($methods as $method) {
            if (is_callable([$test, $method])) {
                call_user_func([$test, $method]);
            }
        }
    }
}
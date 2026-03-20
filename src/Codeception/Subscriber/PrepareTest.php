<?php

declare (strict_types=1);
namespace Codeception\Subscriber;

use Codeception\Event\Test_Event;
use Codeception\Events;
use Codeception\Lib\Di;
use Codeception\Test\Cest;
use Codeception\Test\Unit;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
class Prepare_Test implements Event_Subscriber_Interface
{
    use Shared\Static_Events_Trait;
    /**
     * @var array<string, string>
     */
    protected static array $events = [Events::TEST_BEFORE => 'prepare'];
    protected array $modules = [];
    public function prepare(Test_Event $event): void
    {
        $test = $event->get_test();
        $prepare_methods = $test->get_metadata()->get_param('prepare') ?: [];
        if ($prepare_methods === []) {
            return;
        }
        /** @var Di $di */
        $di = $test->get_metadata()->get_service('di');
        foreach ($prepare_methods as $method) {
            if ($test instanceof Cest) {
                $di->inject_dependencies($test->get_test_instance(), $method);
            }
            if ($test instanceof Unit) {
                $di->inject_dependencies($test, $method);
            }
        }
    }
}
<?php

declare (strict_types=1);
namespace Codeception\Subscriber;

use Codeception\Event\Test_Event;
use Codeception\Events;
use Codeception\Test\Descriptor;
use Codeception\Test\Interfaces\Dependent;
use Codeception\Test_Interface;
use function in_array;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
class Dependencies implements Event_Subscriber_Interface
{
    use Shared\Static_Events_Trait;
    /**
     * @var array<string, string>
     */
    protected static array $events = [Events::TEST_START => 'testStart', Events::TEST_SUCCESS => 'testSuccess'];
    /**
     * @var string[]
     */
    protected array $successful_tests = [];
    public function test_start(Test_Event $event): void
    {
        $test = $event->get_test();
        if (!$test instanceof Dependent) {
            return;
        }
        foreach ($test->fetch_dependencies() as $dep) {
            if (!in_array($dep, $this->successful_tests, true) && $test instanceof Test_Interface) {
                $test->get_metadata()->set_skip("This test depends on {$dep} to pass");
                return;
            }
        }
    }
    public function test_success(Test_Event $event): void
    {
        $this->successful_tests[] = Descriptor::get_test_signature($event->get_test());
    }
}
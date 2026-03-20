<?php

declare (strict_types=1);
namespace Codeception\Subscriber;

use function array_reverse;
use Codeception\Event\Fail_Event;
use Codeception\Event\Step_Event;
use Codeception\Event\Suite_Event;
use Codeception\Event\Test_Event;
use Codeception\Events;
use Codeception\Exception\Throwable_Wrapper;
use Codeception\Suite;
use Codeception\Test_Interface;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
class Module implements Event_Subscriber_Interface
{
    use Shared\Static_Events_Trait;
    /**
     * @var array<string, string>
     */
    protected static array $events = [Events::TEST_BEFORE => 'before', Events::TEST_AFTER => 'after', Events::STEP_BEFORE => 'beforeStep', Events::STEP_AFTER => 'afterStep', Events::TEST_FAIL => 'failed', Events::TEST_ERROR => 'failed', Events::SUITE_BEFORE => 'beforeSuite', Events::SUITE_AFTER => 'afterSuite'];
    /**
     * @param \Codeception\Module[] $modules
     */
    public function __construct(protected array $modules = [])
    {
    }
    public function before_suite(Suite_Event $event): void
    {
        $suite = $event->get_suite();
        if (!$suite instanceof Suite) {
            return;
        }
        $this->modules = $suite->get_modules();
        foreach ($this->modules as $module) {
            $module->_before_suite($event->get_settings());
        }
    }
    public function after_suite(): void
    {
        foreach (array_reverse($this->modules) as $module) {
            $module->_after_suite();
        }
    }
    public function before(Test_Event $event): void
    {
        if (!$event->get_test() instanceof Test_Interface) {
            return;
        }
        foreach ($this->modules as $module) {
            $module->_before($event->get_test());
        }
    }
    public function after(Test_Event $event): void
    {
        if (!$event->get_test() instanceof Test_Interface) {
            return;
        }
        foreach (array_reverse($this->modules) as $module) {
            $module->_after($event->get_test());
            $module->_reset_config();
        }
    }
    public function failed(Fail_Event $event): void
    {
        if (!$event->get_test() instanceof Test_Interface) {
            return;
        }
        foreach (array_reverse($this->modules) as $module) {
            $exception = $event->get_fail();
            if (!$exception instanceof \Exception) {
                /**
                 * @TODO Change _failed parameter to \Throwable in the next major version
                 */
                $exception = new Throwable_Wrapper($exception);
            }
            $module->_failed($event->get_test(), $exception);
        }
    }
    public function before_step(Step_Event $event): void
    {
        foreach ($this->modules as $module) {
            $module->_before_step($event->get_step());
        }
    }
    public function after_step(Step_Event $event): void
    {
        foreach (array_reverse($this->modules) as $module) {
            $module->_after_step($event->get_step());
        }
    }
}
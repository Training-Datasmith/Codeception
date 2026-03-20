<?php

declare (strict_types=1);
namespace Codeception\Reporter;

use Codeception\Event\Fail_Event;
use Codeception\Event\Print_Result_Event;
use Codeception\Event\Test_Event;
use Codeception\Events;
use Codeception\Lib\Console\Message;
use Codeception\Lib\Console\Output;
use Codeception\Lib\Interfaces\Console_Printer;
use Codeception\Subscriber\Shared\Static_Events_Trait;
use Codeception\Test\Descriptor;
use Codeception\Test\Test;
class Report_Printer implements Console_Printer
{
    use Static_Events_Trait;
    /**
     * @var array<string, string>
     */
    protected static array $events = [Events::TEST_SUCCESS => 'testSuccess', Events::TEST_FAIL => 'testFailure', Events::TEST_ERROR => 'testError', Events::TEST_INCOMPLETE => 'testIncomplete', Events::TEST_SKIPPED => 'testSkipped', Events::TEST_WARNING => 'testWarning', Events::TEST_USELESS => 'testUseless', Events::RESULT_PRINT_AFTER => 'afterResult'];
    private Output $output;
    private int $successful_count = 0;
    private int $error_count = 0;
    private int $failure_count = 0;
    private int $warning_count = 0;
    private int $skipped_count = 0;
    private int $incomplete_count = 0;
    private int $useless_count = 0;
    public function __construct(array $options)
    {
        $this->output = new Output($options);
    }
    private function message(string $string = ''): Message
    {
        return $this->output->message($string);
    }
    public function test_success(Test_Event $event): void
    {
        $this->print_test_result($event->get_test(), 'Ok');
        ++$this->successful_count;
    }
    public function test_error(Fail_Event $event): void
    {
        $this->print_test_result($event->get_test(), 'ERROR');
        ++$this->error_count;
    }
    public function test_failure(Fail_Event $event): void
    {
        $this->print_test_result($event->get_test(), 'FAIL');
        ++$this->failure_count;
    }
    public function test_warning(Fail_Event $event): void
    {
        $this->print_test_result($event->get_test(), 'WARNING');
        ++$this->warning_count;
    }
    public function test_skipped(Fail_Event $event): void
    {
        $this->print_test_result($event->get_test(), 'Skipped');
        ++$this->skipped_count;
    }
    public function test_incomplete(Fail_Event $event): void
    {
        $this->print_test_result($event->get_test(), 'Incomplete');
        ++$this->incomplete_count;
    }
    public function test_useless(Fail_Event $event): void
    {
        $this->print_test_result($event->get_test(), 'Useless');
        ++$this->useless_count;
    }
    private function print_test_result(Test $test, string $status): void
    {
        $name = Descriptor::get_test_as_string($test);
        if (strlen($name) > 75) {
            $name = substr($name, 0, 70);
        }
        $this->message($name)->width(75, '.')->append($status)->writeln();
    }
    public function after_result(Print_Result_Event $event): void
    {
        $counts = [sprintf('Successful: %s', $this->successful_count)];
        $failed_count = $this->error_count + $this->failure_count + $this->warning_count;
        if ($failed_count > 0) {
            $counts[] = sprintf('Failed: %s', $failed_count);
        }
        if ($this->incomplete_count > 0) {
            $counts[] = sprintf('Incomplete: %s', $this->incomplete_count);
        }
        if ($this->skipped_count > 0) {
            $counts[] = sprintf('Skipped: %s', $this->skipped_count);
        }
        if ($this->useless_count > 0) {
            $counts[] = sprintf('Useless: %s', $this->useless_count);
        }
        $this->output->writeln("\nCodeception Results");
        $this->output->writeln(implode('. ', $counts) . '.');
    }
}
<?php

declare (strict_types=1);
namespace Codeception\Reporter;

use Codeception\Event\Fail_Event;
use Codeception\Event\Print_Result_Event;
use Codeception\Event\Suite_Event;
use Codeception\Event\Test_Event;
use Codeception\Events;
use Codeception\Lib\Console\Output;
use Codeception\Subscriber\Shared\Static_Events_Trait;
use Codeception\Test\Test;
use Codeception\Test\Test_Case_Wrapper;
use Codeception\Util\Stack_Trace_Filter;
use Dom_Document;
use Dom_Element;
use InvalidArgumentException;
use Php_Unit\Framework\Self_Describing;
use Php_Unit\Framework\Test_Failure;
use Php_Unit\Runner\Version as PHPUnitVersion;
use Php_Unit\Util\Throwable_To_String_Mapper;
use Php_Unit\Util\Xml;
use Reflection_Exception;
use function sprintf;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
use Throwable;
class J_Unit_Reporter implements Event_Subscriber_Interface
{
    use Static_Events_Trait;
    /**
     * @var array<string, string>
     */
    protected static array $events = [Events::SUITE_BEFORE => 'beforeSuite', Events::SUITE_AFTER => 'afterSuite', Events::TEST_START => 'startTest', Events::TEST_END => 'endTest', Events::TEST_FAIL => 'testFailure', Events::TEST_ERROR => 'testError', Events::TEST_INCOMPLETE => 'testSkipped', Events::TEST_SKIPPED => 'testSkipped', Events::TEST_USELESS => 'testUseless', Events::TEST_WARNING => 'testWarning', Events::RESULT_PRINT_AFTER => 'afterResult'];
    protected string $report_file_param = 'xml';
    protected string $report_name = 'JUNIT XML';
    protected bool $is_strict = false;
    /**
     * @var string[]
     */
    protected array $strict_attributes = ['file', 'name', 'class'];
    protected Dom_Document $document;
    protected Dom_Element $root;
    /**
     * @var DOMElement[]
     */
    protected array $test_suites = [];
    /**
     * @var int[]
     */
    protected array $test_suite_tests = [0];
    /**
     * @var int[]
     */
    protected array $test_suite_assertions = [0];
    /**
     * @var int[]
     */
    protected array $test_suite_errors = [0];
    /**
     * @var int[]
     */
    protected array $test_suite_failures = [0];
    /**
     * @var int[]
     */
    protected array $test_suite_skipped = [0];
    /**
     * @var int[]
     */
    protected array $test_suite_useless = [0];
    /**
     * @var int[]
     */
    protected array $test_suite_times = [0];
    protected int $test_suite_level = 0;
    protected ?Dom_Element $current_test_case = null;
    private string $report_file;
    public function __construct(array $options, private Output $output)
    {
        $this->report_file = $options[$this->report_file_param];
        if (!codecept_is_path_absolute($this->report_file)) {
            $this->report_file = codecept_output_dir($this->report_file);
        }
        codecept_debug(sprintf('Printing %s report to %s', $this->report_name, $this->report_file));
        $this->is_strict = $options['strict_xml'];
        $this->document = new Dom_Document('1.0', 'UTF-8');
        $this->document->format_output = true;
        $this->root = $this->document->create_element('testsuites');
        $this->document->append_child($this->root);
    }
    public function after_result(Print_Result_Event $event): void
    {
        file_put_contents($this->report_file, $this->document->save_xml());
        $this->output->message('- <bold>%s</bold> report generated in <comment>file://%s</comment>', $this->report_name, $this->report_file)->writeln();
    }
    public function before_suite(Suite_Event $event): void
    {
        $suite = $event->get_suite();
        $test_suite = $this->document->create_element('testsuite');
        $test_suite->set_attribute('name', $suite->get_name());
        if ($this->test_suite_level > 0) {
            $this->test_suites[$this->test_suite_level]->append_child($test_suite);
        } else {
            $this->root->append_child($test_suite);
        }
        ++$this->test_suite_level;
        $this->test_suites[$this->test_suite_level] = $test_suite;
        $this->test_suite_tests[$this->test_suite_level] = 0;
        $this->test_suite_assertions[$this->test_suite_level] = 0;
        $this->test_suite_errors[$this->test_suite_level] = 0;
        $this->test_suite_failures[$this->test_suite_level] = 0;
        $this->test_suite_skipped[$this->test_suite_level] = 0;
        $this->test_suite_useless[$this->test_suite_level] = 0;
        $this->test_suite_times[$this->test_suite_level] = 0;
    }
    public function after_suite(Suite_Event $event): void
    {
        $this->set_test_suite_attributes($this->test_suite_level);
        if ($this->test_suite_level > 1) {
            $this->aggregate_test_suite_data($this->test_suite_level - 1, $this->test_suite_level);
        }
        --$this->test_suite_level;
    }
    private function set_test_suite_attributes(int $level): void
    {
        $test_suite = $this->test_suites[$level];
        $test_suite->set_attribute('tests', (string) $this->test_suite_tests[$level]);
        $test_suite->set_attribute('assertions', (string) $this->test_suite_assertions[$level]);
        $test_suite->set_attribute('errors', (string) $this->test_suite_errors[$level]);
        $test_suite->set_attribute('failures', (string) $this->test_suite_failures[$level]);
        $test_suite->set_attribute('skipped', (string) $this->test_suite_skipped[$level]);
        if (!$this->is_strict) {
            $test_suite->set_attribute('useless', (string) $this->test_suite_useless[$level]);
        }
        $test_suite->set_attribute('time', sprintf('%F', $this->test_suite_times[$level]));
    }
    private function aggregate_test_suite_data(int $parent_level, int $child_level): void
    {
        $this->test_suite_tests[$parent_level] += $this->test_suite_tests[$child_level];
        $this->test_suite_assertions[$parent_level] += $this->test_suite_assertions[$child_level];
        $this->test_suite_errors[$parent_level] += $this->test_suite_errors[$child_level];
        $this->test_suite_failures[$parent_level] += $this->test_suite_failures[$child_level];
        $this->test_suite_skipped[$parent_level] += $this->test_suite_skipped[$child_level];
        $this->test_suite_useless[$parent_level] += $this->test_suite_useless[$child_level];
        $this->test_suite_times[$parent_level] += $this->test_suite_times[$child_level];
    }
    public function start_test(Test_Event $event): void
    {
        $test = $event->get_test();
        $this->current_test_case = $this->document->create_element('testcase');
        foreach ($test->get_report_fields() as $attr => $value) {
            if ($this->is_strict && !in_array($attr, $this->strict_attributes)) {
                continue;
            }
            $this->current_test_case->set_attribute($attr, $value);
        }
    }
    public function end_test(Test_Event $event): void
    {
        $test = $event->get_test();
        $time = $event->get_time();
        $this->current_test_case->set_attribute('time', sprintf('%F', $time));
        $num_assertions = $test->number_of_assertions_performed();
        $this->test_suite_assertions[$this->test_suite_level] += $num_assertions;
        $this->current_test_case->set_attribute('assertions', (string) $num_assertions);
        $test_output = $this->get_test_output($test);
        if ($test_output !== '') {
            $system_out = $this->document->create_element('system-out', Xml::prepare_string($test_output));
            $this->current_test_case->append_child($system_out);
        }
        $this->test_suites[$this->test_suite_level]->append_child($this->current_test_case);
        ++$this->test_suite_tests[$this->test_suite_level];
        $this->test_suite_times[$this->test_suite_level] += $time;
        $this->current_test_case = null;
    }
    private function get_test_output(Test $test): string
    {
        $test_output = '';
        if ($test instanceof Test_Case_Wrapper) {
            $test_case = $test->get_test_case();
            $phpunit_version = Php_Unit_Version::series();
            if (version_compare($phpunit_version, '11.0', '>=')) {
                if (!$test_case->expects_output()) {
                    $test_output = $test_case->output();
                }
            } elseif (version_compare($phpunit_version, '10.3', '>=')) {
                if (!$test_case->expects_output()) {
                    $test_output = $test_case->get_actual_output_for_assertion();
                }
            } elseif (!$test_case->has_expectation_on_output()) {
                $test_output = $test_case->get_actual_output_for_assertion();
            }
        }
        return $test_output;
    }
    public function test_error(Fail_Event $event): void
    {
        $this->add_fault($event->get_test(), $event->get_fail(), 'error');
        ++$this->test_suite_errors[$this->test_suite_level];
    }
    public function test_warning(Fail_Event $event): void
    {
        $this->add_fault($event->get_test(), $event->get_fail(), 'warning');
        ++$this->test_suite_failures[$this->test_suite_level];
    }
    public function test_failure(Fail_Event $event): void
    {
        $this->add_fault($event->get_test(), $event->get_fail(), 'failure');
        ++$this->test_suite_failures[$this->test_suite_level];
    }
    public function test_skipped(Fail_Event $event): void
    {
        if (!$this->current_test_case instanceof Dom_Element) {
            return;
        }
        $skipped = $this->document->create_element('skipped');
        $this->current_test_case->append_child($skipped);
        ++$this->test_suite_skipped[$this->test_suite_level];
    }
    public function test_useless(Fail_Event $event): void
    {
        if (!$this->current_test_case instanceof Dom_Element) {
            return;
        }
        $error = $this->document->create_element('error', 'Useless Test');
        $this->current_test_case->append_child($error);
        ++$this->test_suite_useless[$this->test_suite_level];
    }
    /**
     * Method which generalizes addError() and addFailure()
     *
     * @throws InvalidArgumentException
     * @throws ReflectionException
     */
    private function add_fault(Test $test, Throwable $t, string $type): void
    {
        if (!$this->current_test_case instanceof Dom_Element) {
            return;
        }
        if ($test instanceof Test_Case_Wrapper) {
            $buffer = str_replace(': ', '::test', $test->to_string()) . "\n";
        } elseif ($test instanceof Self_Describing) {
            $buffer = $test->to_string() . "\n";
        } else {
            $buffer = '';
        }
        if (version_compare(Php_Unit_Version::series(), '10.0', '<') && class_exists(Test_Failure::class)) {
            $exception_string = Test_Failure::exception_to_string($t);
        } else {
            $exception_string = Throwable_To_String_Mapper::map($t);
        }
        $buffer .= $exception_string . "\n" . Stack_Trace_Filter::get_filtered_stacktrace($t);
        $fault = $this->document->create_element($type, Xml::prepare_string($buffer));
        $fault->set_attribute('type', $t::class);
        $this->current_test_case->append_child($fault);
    }
}
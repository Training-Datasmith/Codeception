<?php

declare (strict_types=1);
namespace Codeception\Reporter;

use Codeception\Event\Suite_Event;
use Codeception\Event\Test_Event;
use Codeception\Test\Interfaces\Reported;
use Codeception\Test\Test;
use ReflectionClass;
class Php_Unit_Reporter extends J_Unit_Reporter
{
    public const SUITE_LEVEL = 1;
    public const FILE_LEVEL = 2;
    protected string $report_file_param = 'phpunit-xml';
    protected string $report_name = 'PHPUNIT XML';
    private ?string $current_file = null;
    public function start_test(Test_Event $event): void
    {
        $test = $event->get_test();
        $filename = method_exists($test, 'getFileName') ? $test->get_file_name() : (new ReflectionClass($test))->get_file_name();
        if ($filename !== $this->current_file) {
            if ($this->current_file !== null) {
                parent::after_suite(new Suite_Event());
            }
            $this->initialize_file_level_suite($filename, $test);
            $this->current_file = $filename;
        }
        parent::start_test($event);
    }
    private function initialize_file_level_suite(string $filename, Test $test): void
    {
        $this->test_suite_assertions[self::FILE_LEVEL] = 0;
        $this->test_suite_tests[self::FILE_LEVEL] = 0;
        $this->test_suite_times[self::FILE_LEVEL] = 0;
        $this->test_suite_errors[self::FILE_LEVEL] = 0;
        $this->test_suite_failures[self::FILE_LEVEL] = 0;
        $this->test_suite_skipped[self::FILE_LEVEL] = 0;
        $this->test_suite_useless[self::FILE_LEVEL] = 0;
        $this->test_suite_level = self::FILE_LEVEL;
        $current_file_suite_element = $this->document->create_element('testsuite');
        if ($test instanceof Reported) {
            $report_fields = $test->get_report_fields();
            $class = $report_fields['class'] ?? $report_fields['name'];
            $current_file_suite_element->set_attribute('name', $class);
        } else {
            $current_file_suite_element->set_attribute('name', $test::class);
        }
        $current_file_suite_element->set_attribute('file', $filename);
        $this->test_suites[self::SUITE_LEVEL]->append_child($current_file_suite_element);
        $this->test_suites[self::FILE_LEVEL] = $current_file_suite_element;
    }
    /**
     * Cleans the mess caused by test suite manipulation in startTest
     */
    public function after_suite(Suite_Event $event): void
    {
        $suite = $event->get_suite();
        if ($suite->get_name() && $this->current_file) {
            parent::after_suite(new Suite_Event($suite));
            $this->current_file = null;
        }
        $this->test_suite_level = self::SUITE_LEVEL;
        parent::after_suite($event);
    }
}
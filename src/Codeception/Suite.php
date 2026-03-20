<?php

declare (strict_types=1);
namespace Codeception;

use Codeception\Event\Fail_Event;
use Codeception\Event\Suite_Event;
use Codeception\Event\Test_Event;
use Codeception\Test\Descriptor;
use Codeception\Test\Interfaces\Dependent;
use Codeception\Test\Test;
use Codeception\Test\Test_Case_Wrapper;
use function count;
use Php_Unit\Framework\Incomplete_Test_Error;
use Php_Unit\Framework\Skipped_Test_Error;
use Php_Unit\Framework\Skipped_With_Message_Exception;
use Php_Unit\Runner\Version as PHPUnitVersion;
use Php_Unit\Text_Ui\Cli_Arguments\Builder;
use Php_Unit\Text_Ui\Configuration\Registry;
use Php_Unit\Text_Ui\Xml_Configuration\Default_Configuration;
use Symfony\Component\Event_Dispatcher\Event_Dispatcher;
class Suite
{
    /**
     * @var Array<string, Module>
     */
    protected array $modules = [];
    protected ?string $base_name = null;
    private bool $report_useless_tests = false;
    private bool $backup_globals = false;
    private bool $be_strict_about_changes_to_global_state = false;
    private bool $disallow_test_output = false;
    private bool $collect_code_coverage = false;
    /**
     * @var Test[]
     */
    private array $tests = [];
    public function __construct(private readonly Event_Dispatcher $dispatcher, private readonly string $name = '')
    {
    }
    public function get_name(): string
    {
        return $this->name;
    }
    public function report_useless_tests(bool $enabled): void
    {
        $this->report_useless_tests = $enabled;
    }
    public function backup_globals(bool $enabled): void
    {
        $this->backup_globals = $enabled;
    }
    public function be_strict_about_changes_to_global_state(bool $enabled): void
    {
        $this->be_strict_about_changes_to_global_state = $enabled;
    }
    public function disallow_test_output(bool $enabled): void
    {
        $this->disallow_test_output = $enabled;
    }
    public function collect_code_coverage(bool $enabled): void
    {
        $this->collect_code_coverage = $enabled;
    }
    public function run(Result_Aggregator $result): void
    {
        if ($this->tests === []) {
            return;
        }
        $this->dispatcher->dispatch(new Suite_Event($this), 'suite.start');
        foreach ($this->tests as $test) {
            if ($result->should_stop()) {
                break;
            }
            $this->dispatcher->dispatch(new Test_Event($test), Events::TEST_START);
            if ($test instanceof Test_Interface && $test->get_metadata()->is_blocked()) {
                $result->add_test($test);
                $skip = $test->get_metadata()->get_skip();
                if ($skip !== null) {
                    if (version_compare(Php_Unit_Version::series(), '10.0', '<') && class_exists(Skipped_Test_Error::class)) {
                        $exception = new Skipped_Test_Error($skip);
                    } else {
                        $exception = new Skipped_With_Message_Exception($skip);
                    }
                    $fail_event = new Fail_Event($test, $exception, 0);
                    $result->add_skipped($fail_event);
                    $this->dispatcher->dispatch($fail_event, Events::TEST_SKIPPED);
                }
                $incomplete = $test->get_metadata()->get_incomplete();
                if ($incomplete !== null) {
                    $exception = new Incomplete_Test_Error($incomplete);
                    $fail_event = new Fail_Event($test, $exception, 0);
                    $result->add_incomplete($fail_event);
                    $this->dispatcher->dispatch($fail_event, Events::TEST_INCOMPLETE);
                }
                $this->dispatcher->dispatch(new Test_Event($test, 0), Events::TEST_END);
                continue;
            }
            if ($test instanceof Test_Case_Wrapper) {
                $test_case = $test->get_test_case();
                if (Php_Unit_Version::series() < 10) {
                    $test_case->set_be_strict_about_changes_to_global_state($this->be_strict_about_changes_to_global_state);
                    $test_case->set_backup_globals($this->backup_globals);
                }
            }
            $test->set_event_dispatcher($this->dispatcher);
            $test->report_useless_tests($this->report_useless_tests);
            $test->collect_code_coverage($this->collect_code_coverage);
            $test->real_run($result);
        }
    }
    public function reorder_dependencies(): void
    {
        $tests = [];
        foreach ($this->tests as $test) {
            $tests = array_merge($tests, $this->get_dependencies($test));
        }
        $queue = [];
        $hashes = [];
        foreach ($tests as $test) {
            if (in_array(spl_object_hash($test), $hashes, true)) {
                continue;
            }
            $hashes[] = spl_object_hash($test);
            $queue[] = $test;
        }
        $this->tests = $queue;
    }
    protected function get_dependencies(Test $test): array
    {
        if (!$test instanceof Dependent) {
            return [$test];
        }
        $tests = [];
        foreach ($test->fetch_dependencies() as $required_test_name) {
            $required = $this->find_matched_test($required_test_name);
            if (!$required instanceof Test) {
                continue;
            }
            $tests = array_merge($tests, $this->get_dependencies($required));
        }
        $tests[] = $test;
        return $tests;
    }
    protected function find_matched_test(string $test_signature): ?Test
    {
        foreach ($this->tests as $test) {
            $signature = Descriptor::get_test_signature($test);
            if ($signature === $test_signature) {
                return $test;
            }
        }
        return null;
    }
    /**
     * @return Array<string,Module>
     */
    public function get_modules(): array
    {
        return $this->modules;
    }
    /**
     * @param Array<string,Module> $modules
     */
    public function set_modules(array $modules): void
    {
        $this->modules = $modules;
    }
    public function get_base_name(): string
    {
        return $this->base_name;
    }
    public function set_base_name(string $base_name): void
    {
        $this->base_name = $base_name;
    }
    protected function fire(string $event_type, Test_Event $event): void
    {
        $test = $event->get_test();
        foreach ($test->get_metadata()->get_groups() as $group) {
            $this->dispatcher->dispatch($event, $event_type . '.' . $group);
        }
        $this->dispatcher->dispatch($event, $event_type);
    }
    public function add_test(Test $test): void
    {
        $this->tests[] = $test;
    }
    /**
     * @return Test[]
     */
    public function get_tests(): array
    {
        return $this->tests;
    }
    public function get_test_count(): int
    {
        return count($this->tests);
    }
    public function init_php_unit_configuration(): void
    {
        $cli_parameters = [];
        if ($this->backup_globals) {
            $cli_parameters[] = '--globals-backup';
        }
        if ($this->be_strict_about_changes_to_global_state) {
            $cli_parameters[] = '--strict-global-state';
        }
        if ($this->disallow_test_output) {
            $cli_parameters[] = '--disallow-test-output';
        }
        $cli_configuration = (new Builder())->from_parameters($cli_parameters);
        $xml_configuration = Default_Configuration::create();
        Registry::init($cli_configuration, $xml_configuration);
    }
}
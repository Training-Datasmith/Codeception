<?php

declare (strict_types=1);
namespace Codeception\Subscriber;

use function array_map;
use function array_merge;
use function array_reverse;
use function codecept_relative_path;
use Codeception\Event\Fail_Event;
use Codeception\Event\Print_Result_Event;
use Codeception\Event\Step_Event;
use Codeception\Event\Suite_Event;
use Codeception\Event\Test_Event;
use Codeception\Events;
use Codeception\Exception\Useless_Test_Exception;
use Codeception\Lib\Console\Message;
use Codeception\Lib\Console\Message_Factory;
use Codeception\Lib\Console\Output;
use Codeception\Result_Aggregator;
use Codeception\Step;
use Codeception\Step\Comment;
use Codeception\Step\Conditional_Assertion;
use Codeception\Step\Meta;
use Codeception\Subscriber\Shared\Static_Events_Trait;
use Codeception\Suite;
use Codeception\Test\Descriptor;
use Codeception\Test\Interfaces\Scenario_Driven;
use Codeception\Test_Interface;
use Codeception\Util\Debug;
use Codeception\Util\Stack_Trace_Filter;
use function count;
use function exec;
use function getenv;
use function implode;
use function number_format;
use Php_Unit\Framework\Assertion_Failed_Error;
use Php_Unit\Framework\Expectation_Failed_Exception;
use Php_Unit\Framework\Incomplete_Test_Error;
use Php_Unit\Framework\Self_Describing;
use Php_Unit\Framework\Skipped_Test;
use function preg_match;
use function preg_replace;
use function round;
use Sebastian_Bergmann\Comparator\Comparison_Failure;
use Sebastian_Bergmann\Timer\Duration;
use Sebastian_Bergmann\Timer\Resource_Usage_Formatter;
use Sebastian_Bergmann\Timer\Timer;
use function sprintf;
use function strlen;
use function strtoupper;
use function substr;
use Symfony\Component\Console\Formatter\Output_Formatter;
use Symfony\Component\Console\Formatter\Output_Formatter_Style;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
use function ucfirst;
class Console implements Event_Subscriber_Interface
{
    use Static_Events_Trait;
    /**
     * @var array<string, string>
     */
    protected static array $events = [Events::SUITE_BEFORE => 'beforeSuite', Events::SUITE_AFTER => 'afterSuite', Events::TEST_START => 'startTest', Events::TEST_END => 'endTest', Events::STEP_BEFORE => 'beforeStep', Events::TEST_SUCCESS => 'testSuccess', Events::TEST_FAIL => 'testFail', Events::TEST_ERROR => 'testError', Events::TEST_INCOMPLETE => 'testIncomplete', Events::TEST_SKIPPED => 'testSkipped', Events::TEST_WARNING => 'testWarning', Events::TEST_USELESS => 'testUseless', Events::TEST_FAIL_PRINT => 'printFail', Events::RESULT_PRINT_AFTER => 'afterResult'];
    protected ?Meta $meta_step = null;
    protected ?Message $message = null;
    protected bool $steps = true;
    protected bool $debug = false;
    protected bool $ansi = true;
    protected bool $silent = false;
    protected ?Self_Describing $printed_test = null;
    protected bool $raw_stack_trace = false;
    protected int $trace_length = 5;
    protected ?int $width = null;
    protected Output $output;
    protected string $namespace = '';
    /**
     * @var array<string, string>
     */
    protected array $chars = ['success' => '+', 'fail' => 'x', 'of' => ':'];
    /**
     * @var array<string, int|bool|null>
     */
    protected array $options = ['debug' => false, 'ansi' => false, 'steps' => true, 'verbosity' => 0, 'xml' => null, 'phpunit-xml' => null, 'html' => null, 'no-artifacts' => false];
    protected Message_Factory $message_factory;
    private Timer $timer;
    private bool $first_defect_type = true;
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(array $options)
    {
        $this->timer = new Timer();
        $this->timer->start();
        $this->prepare_options($options);
        $this->output = new Output($options);
        $this->message_factory = new Message_Factory($this->output);
        if ($this->debug) {
            Debug::set_output($this->output);
        }
        $this->detect_width();
        if ($this->options['ansi'] && !$this->is_win()) {
            $this->chars['success'] = '✔';
            $this->chars['fail'] = '✖';
        }
    }
    // triggered for scenario based tests: cept, cest
    public function before_suite(Suite_Event $event): void
    {
        $this->namespace = '';
        $settings = $event->get_settings();
        if (isset($settings['namespace'])) {
            $this->namespace = $settings['namespace'];
        }
        $this->message('%s Tests (%d) ')->with(ucfirst($event->get_suite()->get_base_name()), $event->get_suite()->get_test_count())->style('bold')->width($this->width, '-')->prepend("\n")->writeln();
        if ($event->get_suite() instanceof Suite) {
            $message = $this->message(implode(', ', array_map(fn(\Codeception\Module $module): string => $module->_get_name(), $event->get_suite()->get_modules())));
            $message->style('info')->prepend('Modules: ')->writeln(Output_Interface::VERBOSITY_VERBOSE);
        }
        $this->message()->width($this->width, '-')->writeln(Output_Interface::VERBOSITY_VERBOSE);
    }
    // triggered for all tests
    public function start_test(Test_Event $event): void
    {
        $test = $event->get_test();
        $this->printed_test = $test;
        $this->message = null;
        if (!$this->output->is_interactive() && !$this->is_detailed($test)) {
            return;
        }
        $this->write_current_test($test);
        if ($this->is_detailed($test)) {
            $this->output->writeln('');
            $this->message(Descriptor::get_test_signature($test))->style('info')->prepend('Signature: ')->writeln();
            $this->message(codecept_relative_path(Descriptor::get_test_full_name($test)))->style('info')->prepend('Test: ')->writeln();
            if ($this->steps) {
                $this->message('Scenario --')->style('comment')->writeln();
                $this->output->wait_for_debug_output = false;
            }
        }
    }
    public function after_result(Print_Result_Event $event): void
    {
        $result = $event->get_result();
        $this->print_header($result);
        $verbose = $this->options['verbosity'] >= Output_Interface::VERBOSITY_VERBOSE;
        $output_formatter = $this->output->get_formatter();
        $output_formatter->set_style('warning', new Output_Formatter_Style('black', 'yellow'));
        $output_formatter->set_style('success', new Output_Formatter_Style('black', 'green'));
        $this->print_defects($result->errors(), 'error');
        $this->print_defects($result->failures(), 'failure');
        $this->print_defects($result->useless(), 'useless test');
        if ($verbose) {
            $this->print_defects($result->incomplete(), 'incomplete test');
            $this->print_defects($result->skipped(), 'skipped test');
        }
        $this->print_footer($event);
        if ($result->skipped_count() + $result->incomplete_count() > 0 && !$verbose) {
            $this->output->writeln('run with `-v` to get more info about skipped or incomplete tests');
        }
    }
    protected function print_header(Result_Aggregator $result): void
    {
        if ($result->test_count() > 0) {
            $this->print_resource_usage($this->timer->stop());
        }
    }
    private function print_resource_usage(Duration $duration): void
    {
        $formatter = new Resource_Usage_Formatter();
        $this->message($formatter->resource_usage($duration))->writeln();
    }
    /**
     * @param FailEvent[] $defects
     */
    private function print_defects(array $defects, string $type): void
    {
        $count = count($defects);
        if ($count == 0) {
            return;
        }
        if ($this->first_defect_type) {
            $this->first_defect_type = false;
        } else {
            $this->message("\n---------")->writeln();
        }
        $this->message('')->writeln();
        $this->message(sprintf('There %s %d %s%s:', $count == 1 ? 'was' : 'were', $count, $type, $count == 1 ? '' : 's'))->writeln();
        $i = 1;
        foreach ($defects as $defect) {
            $this->print_fail($defect, $i++);
        }
    }
    protected function print_footer(Print_Result_Event $event): void
    {
        $result = $event->get_result();
        $test_count = $result->test_count();
        $assertion_count = $result->assertion_count();
        $this->message('')->writeln();
        if ($test_count === 0) {
            $this->message('No tests executed!')->style('warning')->writeln();
            return;
        }
        if ($result->was_successful_and_no_test_is_useless_or_skipped_or_incomplete()) {
            $message = sprintf('OK (%d test%s, %d assertion%s)', $test_count, $test_count === 1 ? '' : 's', $assertion_count, $assertion_count === 1 ? '' : 's');
            $this->message($message)->style('success')->writeln();
            return;
        }
        $style = 'error';
        if ($result->was_successful()) {
            $style = 'warning';
            $this->message('OK, but incomplete, skipped, or useless tests!')->style($style)->writeln();
        } elseif ($result->error_count() !== 0) {
            $this->message('ERRORS!')->style($style)->writeln();
        } elseif ($result->failure_count() !== 0) {
            $this->message('FAILURES!')->style($style)->writeln();
        } elseif ($result->warning_count() !== 0) {
            $style = 'warning';
            $this->message('WARNINGS!')->style($style)->writeln();
        }
        $counts = [sprintf('Tests: %s', $test_count), sprintf('Assertions: %s', $assertion_count)];
        if ($result->error_count() > 0) {
            $counts[] = sprintf('Errors: %s', $result->error_count());
        }
        if ($result->failure_count() > 0) {
            $counts[] = sprintf('Failures: %s', $result->failure_count());
        }
        if ($result->warning_count() > 0) {
            $counts[] = sprintf('Warnings: %s', $result->warning_count());
        }
        if ($result->skipped_count() > 0) {
            $counts[] = sprintf('Skipped: %s', $result->skipped_count());
        }
        if ($result->incomplete_count() > 0) {
            $counts[] = sprintf('Incomplete: %s', $result->incomplete_count());
        }
        if ($result->useless_count() > 0) {
            $counts[] = sprintf('Useless: %s', $result->useless_count());
        }
        $this->message(implode(', ', $counts) . '.')->style($style)->writeln();
    }
    public function test_success(Test_Event $event): void
    {
        if ($this->is_detailed($event->get_test())) {
            $this->message('PASSED')->center(' ')->style('ok')->append("\n")->writeln();
            return;
        }
        $this->writeln_finished_test($event, $this->message($this->chars['success'])->style('ok'));
    }
    public function end_test(Test_Event $event): void
    {
        $this->meta_step = null;
        $this->printed_test = null;
    }
    public function test_warning(Test_Event $event): void
    {
        if ($this->is_detailed($event->get_test())) {
            $this->message('WARNING')->center(' ')->style('pending')->append("\n")->writeln();
            return;
        }
        $this->writeln_finished_test($event, $this->message('W')->style('pending'));
    }
    public function test_fail(Fail_Event $event): void
    {
        if ($this->is_detailed($event->get_test())) {
            $this->message('FAIL')->center(' ')->style('fail')->append("\n")->writeln();
            return;
        }
        $this->writeln_finished_test($event, $this->message($this->chars['fail'])->style('fail'));
    }
    public function test_error(Fail_Event $event): void
    {
        if ($this->is_detailed($event->get_test())) {
            $this->message('ERROR')->center(' ')->style('fail')->append("\n")->writeln();
            return;
        }
        $this->writeln_finished_test($event, $this->message('E')->style('fail'));
    }
    public function test_skipped(Fail_Event $event): void
    {
        if ($this->is_detailed($event->get_test())) {
            $msg = $event->get_fail()->get_message();
            $this->message('SKIPPED')->append($msg !== '' ? ": {$msg}" : '')->center(' ')->style('pending')->writeln();
            return;
        }
        $this->writeln_finished_test($event, $this->message('S')->style('pending'));
    }
    public function test_incomplete(Fail_Event $event): void
    {
        if ($this->is_detailed($event->get_test())) {
            $msg = $event->get_fail()->get_message();
            $this->message('INCOMPLETE')->append($msg !== '' ? ": {$msg}" : '')->center(' ')->style('pending')->writeln();
            return;
        }
        $this->writeln_finished_test($event, $this->message('I')->style('pending'));
    }
    public function test_useless(Fail_Event $event): void
    {
        $this->writeln_finished_test($event, $this->message('U')->style('pending'));
    }
    protected function is_detailed($test): bool
    {
        if (!$test instanceof Scenario_Driven) {
            return false;
        }
        return $this->steps;
    }
    public function before_step(Step_Event $event): void
    {
        if (!$this->steps || !$event->get_test() instanceof Scenario_Driven) {
            return;
        }
        $meta_step = $event->get_step()->get_meta_step();
        if ($meta_step instanceof Meta && $this->meta_step != $meta_step) {
            $this->message(' ' . $meta_step->get_prefix())->style('bold')->append($meta_step->__toString())->writeln();
        }
        $this->meta_step = $meta_step;
        $this->print_step($event->get_step());
    }
    private function print_step(Step $step): void
    {
        if ($step instanceof Comment && $step->__toString() === '') {
            return;
            // don't print empty comments
        }
        $msg = $this->message(' ');
        if ($this->meta_step instanceof Meta) {
            $msg->append('  ');
        }
        $msg->append($step->get_prefix());
        $prefix_length = $msg->get_length();
        if (!$this->meta_step instanceof Meta) {
            $msg->style('bold');
        }
        $max_length = $this->width - $prefix_length;
        $msg->append(Output_Formatter::escape($step->to_string($max_length)));
        if ($this->meta_step instanceof Meta) {
            $msg->style('info');
        }
        $msg->writeln();
    }
    public function after_suite(Suite_Event $event): void
    {
        $this->message()->width($this->width, '-')->writeln();
    }
    public function print_fail(Fail_Event $event, int $event_number): void
    {
        $failed_test = $event->get_test();
        $fail = $event->get_fail();
        $this->output->write($event_number . ') ');
        $this->write_current_test($failed_test, false);
        $this->output->writeln('');
        // Clickable `editor_url`:
        if (isset($this->options['editor_url']) && is_string($this->options['editor_url'])) {
            $file_path = $failed_test->get_filename();
            $line = 1;
            foreach ($fail->get_trace() as $trace) {
                if (isset($trace['file']) && $file_path === $trace['file'] && isset($trace['line'])) {
                    $line = $trace['line'];
                }
            }
            $message = str_replace(['%%file%%', '%%line%%'], [$file_path, $line], $this->options['editor_url']);
        } else {
            $message = Descriptor::get_test_full_name($failed_test);
        }
        $test_style = 'error';
        if ($fail instanceof Skipped_Test || $fail instanceof Incomplete_Test_Error || $fail instanceof Useless_Test_Exception) {
            $test_style = 'warning';
        }
        $this->message(' Test  ')->style($test_style)->append($message)->write();
        if ($failed_test instanceof Scenario_Driven) {
            $this->print_scenario_fail($failed_test, $fail);
            $this->print_reports($failed_test);
            return;
        }
        $this->print_exception($fail);
        $this->print_exception_trace($fail);
    }
    public function print_reports(Test_Interface $failed_test): void
    {
        if ($this->options['no-artifacts']) {
            return;
        }
        $reports = $failed_test->get_metadata()->get_reports();
        if ($reports !== []) {
            $this->output->writeln('<comment>Artifacts:</comment>');
            $this->output->writeln('');
        }
        foreach ($reports as $type => $report) {
            $type = ucfirst((string) $type);
            $this->output->writeln("{$type}: <debug>{$report}</debug>");
        }
    }
    public function print_exception($exception, ?string $cause = null): void
    {
        if ($exception instanceof Skipped_Test || $exception instanceof Incomplete_Test_Error) {
            if ($exception->get_message() !== '') {
                $this->message(Output_Formatter::escape($exception->get_message()))->prepend("\n")->writeln();
            }
            return;
        }
        $class = $exception::class;
        if (str_starts_with($class, 'Codeception\Exception')) {
            $class = substr($class, strlen('Codeception\Exception\\'));
        }
        $this->output->writeln('');
        $message = $this->message(Output_Formatter::escape($exception->get_message()));
        if ($exception instanceof Expectation_Failed_Exception) {
            $comparison_failure = $exception->get_comparison_failure();
            if ($comparison_failure instanceof Comparison_Failure) {
                $message->append($this->message_factory->prepare_comparison_failure_message($comparison_failure));
            }
        }
        $is_failure = $exception instanceof Assertion_Failed_Error || $class === Expectation_Failed_Exception::class || $class === Assertion_Failed_Error::class;
        if (!$is_failure) {
            $message->prepend("[{$class}] ")->block('error');
        }
        if ($is_failure && $cause) {
            $cause = Output_Formatter::escape(ucfirst($cause));
            $message->prepend("<error> Step </error> {$cause}\n<error> Fail </error> ");
        }
        $message->writeln();
    }
    public function print_scenario_fail(Scenario_Driven $failed_test, $fail): void
    {
        $failed_step = (string) $failed_test->get_scenario()->get_meta_step();
        if ($failed_step === '') {
            foreach (array_reverse($failed_test->get_scenario()->get_steps()) as $step) {
                if ($step->has_failed()) {
                    $failed_step = (string) $step;
                    break;
                }
            }
        }
        $this->print_exception($fail, $failed_step);
        $this->print_scenario_trace($failed_test);
        if ($this->output->get_verbosity() == Output_Interface::VERBOSITY_DEBUG) {
            $this->print_exception_trace($fail);
            return;
        }
        if (!$fail instanceof Assertion_Failed_Error) {
            $this->print_exception_trace($fail);
        }
    }
    public function print_exception_trace($exception): void
    {
        static $limit = 10;
        if ($exception instanceof Skipped_Test || $exception instanceof Incomplete_Test_Error || $exception instanceof Useless_Test_Exception) {
            return;
        }
        if ($this->raw_stack_trace) {
            $this->message(Output_Formatter::escape(Stack_Trace_Filter::get_filtered_stacktrace($exception, true, false)))->writeln();
            return;
        }
        $trace = Stack_Trace_Filter::get_filtered_stacktrace($exception, false);
        $i = 0;
        foreach ($trace as $step) {
            if ($i >= $limit) {
                break;
            }
            ++$i;
            $message = $this->message((string) $i)->prepend('#')->width(4);
            if (!isset($step['file'])) {
                foreach (['class', 'type', 'function'] as $info) {
                    if (!isset($step[$info])) {
                        continue;
                    }
                    $message->append($step[$info]);
                }
                $message->writeln();
                continue;
            }
            // Clickable `editor_url`:
            if (isset($this->options['editor_url']) && is_string($this->options['editor_url'])) {
                $line_string = str_replace(['%%file%%', '%%line%%'], [$step['file'], $step['line']], $this->options['editor_url']);
            } else {
                $line_string = $step['file'] . ':' . $step['line'];
            }
            $message->append($line_string);
            $message->writeln();
        }
        $prev = $exception->get_previous();
        if ($prev) {
            $this->print_exception_trace($prev);
        }
    }
    public function print_scenario_trace(Scenario_Driven $failed_test): void
    {
        $trace = array_reverse($failed_test->get_scenario()->get_steps());
        $length = count($trace);
        $step_number = $length;
        if ($length === 0) {
            return;
        }
        $this->message("\nScenario Steps:\n")->style('comment')->writeln();
        foreach ($trace as $step) {
            /** @var Step $step */
            if (!$step->__toString()) {
                continue;
            }
            $message = $this->message((string) $step_number)->prepend(' ')->width(strlen((string) $length))->append('. ');
            $message->append(Output_Formatter::escape($step->get_php_code($this->width - $message->get_length())));
            if ($step->has_failed()) {
                $message->style('bold');
            }
            if (!$step instanceof Comment) {
                $file_path = $step->get_file_path();
                if ($file_path) {
                    // Clickable `editor_url`:
                    if (isset($this->options['editor_url']) && is_string($this->options['editor_url'])) {
                        $line_string = str_replace(['%%file%%', '%%line%%'], [codecept_absolute_path($step->get_file_path()), $step->get_line_number()], $this->options['editor_url']);
                    } else {
                        $line_string = $step->get_file_path() . ':' . $step->get_line_number();
                    }
                    $message->append(" at <info>{$line_string}</info>");
                }
            }
            --$step_number;
            $message->writeln();
            if ($length - $step_number - 1 >= $this->trace_length) {
                break;
            }
        }
        $this->output->writeln('');
    }
    public function detect_width(): int
    {
        $this->width = 60;
        if (!$this->is_win() && PHP_SAPI === 'cli' && getenv('TERM') && getenv('TERM') != 'unknown') {
            // try to get terminal width from ENV variable (bash), see also https://github.com/Codeception/Codeception/issues/3788
            if (getenv('COLUMNS')) {
                $this->width = (int) getenv('COLUMNS');
            } else {
                $this->width = (int) shell_exec('command -v tput >> /dev/null 2>&1 && tput cols') - 2;
            }
        } elseif ($this->is_win() && PHP_SAPI === 'cli') {
            exec('mode con', $output);
            if (isset($output[4])) {
                preg_match('#^ +.* +(\d+)$#', $output[4], $matches);
                if (!empty($matches[1])) {
                    $this->width = (int) $matches[1];
                }
            }
        }
        return $this->width;
    }
    private function is_win(): bool
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }
    protected function write_current_test(Self_Describing $test, bool $in_progress = true): void
    {
        $prefix = $this->output->is_interactive() && !$this->is_detailed($test) && $in_progress ? '- ' : '';
        $test_string = Descriptor::get_test_as_string($test);
        $test_string = preg_replace('#^([^:]+):\s#', "<focus>\$1{$this->chars['of']}</focus> ", $test_string);
        $this->message($test_string)->prepend($prefix)->write();
    }
    protected function writeln_finished_test(Test_Event $event, Message $result): void
    {
        /** @var SelfDescribing $test */
        $test = $event->get_test();
        if ($this->is_detailed($test)) {
            return;
        }
        if ($this->output->is_interactive()) {
            $this->output->write("\r");
        }
        $result->append(' ')->write();
        $this->write_current_test($test, false);
        if (method_exists($test, 'getScenario')) {
            $num_fails = count(array_filter($test->get_scenario()?->get_steps() ?? [], fn(Step $step): bool => $step->has_failed() && $step instanceof Conditional_Assertion));
            $conditional_fails_message = '';
            if ($num_fails == 1) {
                $conditional_fails_message = '[F]';
            } elseif ($num_fails !== 0) {
                $conditional_fails_message = "{$num_fails}x[F]";
            }
            if ($conditional_fails_message !== '') {
                $conditional_fails_message = " <error>{$conditional_fails_message}</error> ";
                $this->message($conditional_fails_message)->write();
            }
        }
        $this->write_time_information($event);
        $this->output->writeln('');
    }
    private function message(string $string = ''): Message
    {
        return $this->message_factory->message($string);
    }
    protected function write_time_information(Test_Event $event): void
    {
        $time = $event->get_time();
        if ($time !== 0.0) {
            $this->message(number_format(round($time, 2), 2))->prepend(' (')->append('s)')->style('info')->write();
        }
    }
    private function prepare_options(array $options): void
    {
        $this->options = array_merge($this->options, $options);
        $this->debug = $this->options['debug'] || $this->options['verbosity'] >= Output_Interface::VERBOSITY_VERY_VERBOSE;
        $this->steps = $this->debug || $this->options['steps'];
        $this->raw_stack_trace = $this->options['verbosity'] === Output_Interface::VERBOSITY_DEBUG;
    }
}
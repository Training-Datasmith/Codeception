<?php

declare (strict_types=1);
namespace Codeception\Reporter;

use Codeception\Event\Fail_Event;
use Codeception\Event\Print_Result_Event;
use Codeception\Event\Suite_Event;
use Codeception\Event\Test_Event;
use Codeception\Events;
use Codeception\Lib\Console\Output;
use Codeception\Step;
use Codeception\Step\Meta;
use Codeception\Subscriber\Shared\Static_Events_Trait;
use Codeception\Test\Descriptor;
use Codeception\Test\Interfaces\Scenario_Driven;
use Codeception\Test\Test;
use Codeception\Util\Path_Resolver;
use Sebastian_Bergmann\Template\Template;
use Sebastian_Bergmann\Timer\Timer;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
use function trigger_error;
class Html_Reporter implements Event_Subscriber_Interface
{
    use Static_Events_Trait;
    /**
     * @var array<string, string>
     */
    protected static array $events = [Events::SUITE_BEFORE => 'beforeSuite', Events::RESULT_PRINT_AFTER => 'afterResult', Events::TEST_SUCCESS => 'testSuccess', Events::TEST_FAIL => 'testFailure', Events::TEST_ERROR => 'testError', Events::TEST_INCOMPLETE => 'testIncomplete', Events::TEST_SKIPPED => 'testSkipped', Events::TEST_USELESS => 'testUseless', Events::TEST_WARNING => 'testWarning'];
    protected int $id = 0;
    protected string $scenarios = '';
    protected string $template_path;
    private string $report_file;
    private Timer $timer;
    public function __construct(array $options, private Output $output)
    {
        $this->report_file = $options['html'];
        if (!codecept_is_path_absolute($this->report_file)) {
            $this->report_file = codecept_output_dir($this->report_file);
        }
        codecept_debug(sprintf('Printing HTML report to %s', $this->report_file));
        $this->template_path = sprintf('%s%stemplate%s', __DIR__, DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR);
        $this->timer = new Timer();
        $this->timer->start();
    }
    public function before_suite(Suite_Event $event): void
    {
        $suite = $event->get_suite();
        if (!$suite->get_name()) {
            return;
        }
        $suite_template = new Template($this->template_path . 'suite.html');
        $suite_template->set_var(['suite' => ucfirst($suite->get_name())]);
        $this->scenarios .= $suite_template->render();
    }
    public function test_success(Test_Event $event): void
    {
        $this->print_test_event($event, 'scenarioSuccess');
    }
    public function test_error(Fail_Event $event): void
    {
        $this->print_test_event($event, 'scenarioFailed');
    }
    public function test_failure(Fail_Event $event): void
    {
        $this->print_test_event($event, 'scenarioFailed');
    }
    public function test_warning(Fail_Event $event): void
    {
        $this->print_test_event($event, 'scenarioSuccess');
    }
    public function test_skipped(Fail_Event $event): void
    {
        $this->print_test_event($event, 'scenarioSkipped');
    }
    public function test_incomplete(Fail_Event $event): void
    {
        $this->print_test_event($event, 'scenarioIncomplete');
    }
    public function test_useless(Fail_Event $event): void
    {
        $this->print_test_event($event, 'scenarioUseless');
    }
    public function print_test_event(Test_Event $event, string $scenario_status): void
    {
        $test = $event->get_test();
        $failure = '';
        if ($event instanceof Fail_Event) {
            $fail_template = new Template($this->template_path . 'fail.html');
            $fail_template->set_var(['fail' => nl2br($event->get_fail()->get_message())]);
            $failure = $fail_template->render() . PHP_EOL;
        }
        $steps_buffer = $this->build_steps_buffer($test);
        $artifacts = $this->get_failure_artifacts($test);
        $png = $artifacts['png'];
        $html = $artifacts['html'];
        $toggle = $steps_buffer ? '<span class="toggle">+</span>' : '';
        $test_string = $this->format_test_name($test);
        $this->scenarios .= $this->render_scenario_template(['id' => ++$this->id, 'name' => $test_string, 'scenarioStatus' => $scenario_status, 'steps' => $steps_buffer, 'toggle' => $toggle, 'failure' => $failure, 'png' => $png, 'html' => $html, 'time' => round($event->get_time(), 2)]);
    }
    /**
     * @deprecated This method is deprecated and will be removed in Codeception 6.0.
     */
    public function print_test_result(Test $test, float $time, string $scenario_status): void
    {
        trigger_error(__METHOD__ . ' is deprecated, please use printTestEvent instead', E_USER_DEPRECATED);
        $steps_buffer = $this->build_steps_buffer($test);
        $artifacts = $this->get_failure_artifacts($test);
        $png = $artifacts['png'];
        $html = $artifacts['html'];
        $toggle = $steps_buffer ? '<span class="toggle">+</span>' : '';
        $test_string = $this->format_test_name($test);
        $this->scenarios .= $this->render_scenario_template(['id' => ++$this->id, 'name' => $test_string, 'scenarioStatus' => $scenario_status, 'steps' => $steps_buffer, 'toggle' => $toggle, 'failure' => '', 'png' => $png, 'html' => $html, 'time' => round($time, 2)]);
    }
    private function build_steps_buffer(Test $test): string
    {
        $steps = [];
        if ($test instanceof Scenario_Driven) {
            $steps = $test->get_scenario()->get_steps();
        }
        $steps_buffer = '';
        $sub_steps_rendered = [];
        foreach ($steps as $step) {
            $meta_step = $step->get_meta_step();
            if ($meta_step) {
                $key = $this->get_meta_step_key($meta_step);
                $sub_steps_rendered[$key][] = $this->render_step($step);
            }
        }
        foreach ($steps as $step) {
            $meta_step = $step->get_meta_step();
            if ($meta_step) {
                $key = $this->get_meta_step_key($meta_step);
                if (isset($sub_steps_rendered[$key]) && $sub_steps_rendered[$key] !== []) {
                    $sub_steps_buffer = implode('', $sub_steps_rendered[$key]);
                    unset($sub_steps_rendered[$key]);
                    $steps_buffer .= $this->render_substeps($step->get_meta_step(), $sub_steps_buffer);
                }
            } else {
                $steps_buffer .= $this->render_step($step);
            }
        }
        return $steps_buffer;
    }
    private function get_failure_artifacts(Test $test): array
    {
        $png = '';
        $html = '';
        $reports = $test->get_metadata()->get_reports();
        if (isset($reports['png'])) {
            $local_path = Path_Resolver::get_relative_dir($reports['png'], codecept_output_dir());
            $png = "<tr><td class='error'><div class='screenshot'><img src='{$local_path}' alt='failure screenshot'></div></td></tr>";
        }
        if (isset($reports['html'])) {
            $local_path = Path_Resolver::get_relative_dir($reports['html'], codecept_output_dir());
            $html = "<tr><td class='error'>See <a href='{$local_path}' target='_blank'>HTML snapshot</a> of a failed page</td></tr>";
        }
        return ['png' => $png, 'html' => $html];
    }
    private function format_test_name(Test $test): string
    {
        $test_string = htmlspecialchars(ucfirst(Descriptor::get_test_as_string($test)), ENT_QUOTES | ENT_SUBSTITUTE);
        return preg_replace('~^([\s\w\\\\]+):\s~', '<span class="quiet">$1 &raquo;</span> ', $test_string);
    }
    private function get_meta_step_key(Meta $meta_step): string
    {
        $key = '';
        $file_path = $meta_step->get_file_path();
        if ($file_path !== null) {
            $key = $file_path;
            $line_number = $meta_step->get_line_number();
            if ($line_number !== null) {
                $key .= ':' . $line_number;
            }
        }
        return $key . $meta_step->get_action();
    }
    protected function render_step(Step $step): string
    {
        $step_template = new Template($this->template_path . 'step.html');
        $step_template->set_var(['action' => $step->get_html(), 'error' => $step->has_failed() ? 'failedStep' : '']);
        return $step_template->render();
    }
    protected function render_substeps(Meta $meta_step, string $substeps_buffer): string
    {
        $meta_template = new Template($this->template_path . 'substeps.html');
        $meta_template->set_var(['metaStep' => $meta_step->get_html(), 'error' => $meta_step->has_failed() ? 'failedStep' : '', 'steps' => $substeps_buffer, 'id' => uniqid()]);
        return $meta_template->render();
    }
    private function render_scenario_template(array $vars): string
    {
        $scenario_template = new Template($this->template_path . 'scenario.html');
        $scenario_template->set_var($vars);
        return $scenario_template->render();
    }
    public function after_result(Print_Result_Event $event): void
    {
        $time_taken = $this->timer->stop()->as_string();
        $result = $event->get_result();
        $status = $result->was_successful_ignoring_warnings() ? '<span style="color: green">OK</span>' : '<span style="color: #e74c3c">FAILED</span>';
        $scenario_header_template = new Template($this->template_path . 'scenario_header.html');
        $scenario_header_template->set_var(['name' => 'Codeception Results', 'status' => $status, 'time' => $time_taken]);
        $header = $scenario_header_template->render();
        $scenarios_template = new Template($this->template_path . 'scenarios.html');
        $scenarios_template->set_var(['header' => $header, 'scenarios' => $this->scenarios, 'successfulScenarios' => $result->successful_count(), 'failedScenarios' => $result->failure_count(), 'skippedScenarios' => $result->skipped_count(), 'incompleteScenarios' => $result->incomplete_count(), 'uselessScenarios' => $result->useless_count()]);
        file_put_contents($this->report_file, $scenarios_template->render());
        $this->output->message('- <bold>HTML</bold> report generated in <comment>file://%s</comment>', $this->report_file)->writeln();
    }
}
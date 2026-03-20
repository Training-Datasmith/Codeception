<?php

declare (strict_types=1);
namespace Codeception;

use Codeception\Event\Fail_Event;
use Codeception\Event\Step_Event;
use Codeception\Exception\Conditional_Assertion_Failed;
use Codeception\Exception\Injection_Exception;
use Codeception\Step\Comment;
use Codeception\Step\Meta;
use Codeception\Test\Metadata;
use Php_Unit\Framework\Incomplete_Test_Error;
use Php_Unit\Framework\Skipped_Test_Error;
use Php_Unit\Framework\Skipped_With_Message_Exception;
use Php_Unit\Runner\Version as PHPUnitVersion;
class Scenario
{
    protected Metadata $metadata;
    /** @var Step[] */
    protected array $steps = [];
    protected string $feature;
    protected ?Meta $meta_step = null;
    public function __construct(protected Test_Interface $test)
    {
        $this->metadata = $this->test->get_metadata();
    }
    public function set_feature(string $feature): void
    {
        $this->metadata->set_feature($feature);
    }
    public function get_feature(): string
    {
        return $this->metadata->get_feature();
    }
    public function get_groups(): array
    {
        return $this->metadata->get_groups();
    }
    public function current(?string $key = null): mixed
    {
        return $this->metadata->get_current($key);
    }
    /**
     * @throws InjectionException
     */
    public function run_step(Step $step): mixed
    {
        $step->save_trace();
        if ($this->meta_step instanceof Meta) {
            $step->set_meta_step($this->meta_step);
        }
        $this->steps[] = $step;
        $dispatcher = $this->metadata->get_service('dispatcher');
        $dispatcher->dispatch(new Step_Event($this->test, $step), Events::STEP_BEFORE);
        try {
            $result = $step->run($this->metadata->get_service('modules'));
        } catch (Conditional_Assertion_Failed $failure) {
            $this->test->get_result_aggregator()->add_failure(new Fail_Event(clone $this->test, $failure, 0));
            $result = null;
        } finally {
            $dispatcher->dispatch(new Step_Event($this->test, $step), Events::STEP_AFTER);
            $step->executed = true;
        }
        return $result;
    }
    public function add_step(Step $step): void
    {
        $this->steps[] = $step;
    }
    /** @return Step[] */
    public function get_steps(): array
    {
        return $this->steps;
    }
    public function get_html(): string
    {
        $text = '';
        foreach ($this->steps as $step) {
            if ($step->get_name() === 'Comment') {
                $text .= trim($step->get_humanized_arguments(), '"') . '<br/>';
            } else {
                $text .= $step->get_html() . '<br/>';
            }
        }
        $text = str_replace(['"\'', '\'"'], ["'", "'"], $text);
        return '<h3>' . mb_strtoupper('I want to ' . $this->get_feature(), 'utf-8') . '</h3>' . $text;
    }
    public function get_text(): string
    {
        $text = '';
        foreach ($this->steps as $step) {
            $text .= $step->get_prefix() . "{$step} \r\n";
        }
        $text = trim(str_replace(['"\'', '\'"'], ["'", "'"], $text));
        return mb_strtoupper('I want to ' . $this->get_feature(), 'utf-8') . "\r\n\r\n" . $text . "\r\n\r\n";
    }
    public function comment(string $comment): void
    {
        $this->run_step(new Comment($comment, []));
    }
    public function skip(string $message = ''): void
    {
        if (version_compare(Php_Unit_Version::series(), '10.0', '<') && class_exists(Skipped_Test_Error::class)) {
            throw new Skipped_Test_Error($message);
        }
        throw new Skipped_With_Message_Exception($message);
    }
    public function incomplete(string $message = ''): void
    {
        throw new Incomplete_Test_Error($message);
    }
    public function set_meta_step(?Meta $meta_step): void
    {
        $this->meta_step = $meta_step;
    }
    public function get_meta_step(): ?Meta
    {
        return $this->meta_step;
    }
}
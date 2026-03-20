<?php

declare (strict_types=1);
namespace Codeception;

use Codeception\Event\Fail_Event;
use Codeception\Test\Test;
class Result_Aggregator
{
    /**
     * @var bool Stop execution of test suite if this property is true
     */
    private bool $stop = false;
    /**
     * @var FailEvent[]
     */
    private array $failures = [];
    /**
     * @var FailEvent[]
     */
    private array $errors = [];
    /**
     * @var FailEvent[]
     */
    private array $warnings = [];
    /**
     * @var FailEvent[]
     */
    private array $useless = [];
    /**
     * @var FailEvent[]
     */
    private array $skipped = [];
    /**
     * @var FailEvent[]
     */
    private array $incomplete = [];
    private int $count = 0;
    private int $successful = 0;
    private int $assertions = 0;
    public function stop(): void
    {
        $this->stop = true;
    }
    public function should_stop(): bool
    {
        return $this->stop;
    }
    public function add_test(Test $test): void
    {
        ++$this->count;
    }
    public function add_successful(Test $test): void
    {
        ++$this->successful;
    }
    public function add_failure(Fail_Event $e): void
    {
        $this->failures[] = $e;
    }
    public function add_error(Fail_Event $e): void
    {
        $this->errors[] = $e;
    }
    public function add_warning(Fail_Event $e): void
    {
        $this->warnings[] = $e;
    }
    public function add_skipped(Fail_Event $e): void
    {
        $this->skipped[] = $e;
    }
    public function add_incomplete(Fail_Event $e): void
    {
        $this->incomplete[] = $e;
    }
    public function add_useless(Fail_Event $e): void
    {
        $this->useless[] = $e;
    }
    public function add_to_assertion_count(int $n): void
    {
        $this->assertions += $n;
    }
    /**
     * @return FailEvent[]
     */
    public function failures(): array
    {
        return $this->failures;
    }
    /**
     * @return FailEvent[]
     */
    public function errors(): array
    {
        return $this->errors;
    }
    /**
     * @return FailEvent[]
     */
    public function useless(): array
    {
        return $this->useless;
    }
    /**
     * @return FailEvent[]
     */
    public function incomplete(): array
    {
        return $this->incomplete;
    }
    /**
     * @return FailEvent[]
     */
    public function skipped(): array
    {
        return $this->skipped;
    }
    public function was_successful(): bool
    {
        return $this->error_count() + $this->failure_count() + $this->warning_count() === 0;
    }
    public function was_successful_ignoring_warnings(): bool
    {
        return $this->error_count() + $this->failure_count() === 0;
    }
    /**
     * @deprecated replaced by wasSuccessfulAndNoTestIsUselessOrSkippedOrIncomplete
     */
    public function was_successful_and_no_test_is_risky_or_skipped_or_incomplete(): bool
    {
        return $this->was_successful_and_no_test_is_useless_or_skipped_or_incomplete();
    }
    public function was_successful_and_no_test_is_useless_or_skipped_or_incomplete(): bool
    {
        return $this->was_successful() && $this->useless_count() + $this->skipped_count() + $this->incomplete_count() === 0;
    }
    public function test_count(): int
    {
        return $this->count;
    }
    public function successful_count(): int
    {
        return $this->successful;
    }
    public function assertion_count(): int
    {
        return $this->assertions;
    }
    public function skipped_count(): int
    {
        return count($this->skipped);
    }
    public function incomplete_count(): int
    {
        return count($this->incomplete);
    }
    public function error_count(): int
    {
        return count($this->errors);
    }
    public function failure_count(): int
    {
        return count($this->failures);
    }
    public function warning_count(): int
    {
        return count($this->warnings);
    }
    public function useless_count(): int
    {
        return count($this->useless);
    }
    public function pop_last_failure(): ?Fail_Event
    {
        return array_pop($this->failures);
    }
    public function get_last_failure(): ?Fail_Event
    {
        return end($this->failures) ?: null;
    }
}
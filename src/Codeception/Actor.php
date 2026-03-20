<?php

declare (strict_types=1);
namespace Codeception;

use Closure;
use Codeception\Lib\Actor\Shared\Comment;
use Codeception\Lib\Actor\Shared\Pause;
use Codeception\Step\Executor;
use RuntimeException;
abstract class Actor
{
    use Comment;
    use Pause;
    public function __construct(protected readonly Scenario $scenario)
    {
    }
    protected function get_scenario(): Scenario
    {
        return $this->scenario;
    }
    /**
     * This method is used by Cept format to add description to test output
     *
     * It can be used by Cest format too.
     * It doesn't do anything when called, but it is parsed by Parser before execution
     *
     * @see \Codeception\Lib\Parser::parseFeature
     */
    public function want_to(string $text): void
    {
    }
    public function want_to_test(string $text): void
    {
    }
    public function __call(string $method, array $arguments): mixed
    {
        throw new RuntimeException(sprintf('Call to undefined method %s::%s', static::class, $method));
    }
    /**
     * Lazy-execution given anonymous function
     */
    public function execute(Closure $callable): self
    {
        $this->scenario->add_step(new Executor($callable, []));
        $callable();
        return $this;
    }
}
<?php

declare(strict_types=1);

namespace CodeceptionPhpunit\Tests\Di;

use Codeception\Exception\InjectionException;
use Codeception\Lib\Di;
use CodeceptionPhpunit\Support\PhpunitTestCase;

final class DiTest extends PhpunitTestCase
{
    private Di $di;

    protected function setUp(): void
    {
        parent::setUp();
        $this->di = new Di();
    }

    public function testNonExistentDependencyFails(): void
    {
        $this->expectException(InjectionException::class);
        $this->di->instantiate(\FailDependenciesNonExistent\IncorrectDependenciesClass::class);
    }

    public function testCyclicDependenciesFail(): void
    {
        $this->expectException(InjectionException::class);
        $this->di->instantiate(\FailDependenciesCyclic\IncorrectDependenciesClass::class);
    }
}

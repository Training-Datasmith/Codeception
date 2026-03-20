<?php

declare (strict_types=1);
namespace Codeception\Step;

use Closure;
use Codeception\Lib\Module_Container;
use Codeception\Step as CodeceptionStep;
class Executor extends Codeception_Step
{
    public function __construct(protected Closure $callable, array $arguments = [])
    {
        parent::__construct('execute callable function', $arguments);
    }
    public function run(?Module_Container $container = null)
    {
        return ($this->callable)();
    }
}
<?php

declare (strict_types=1);
namespace Codeception\Step;

use Codeception\Lib\Module_Container;
use Codeception\Step as CodeceptionStep;
use Php_Unit\Framework\Incomplete_Test_Error;
class Incomplete extends Codeception_Step
{
    public function run(?Module_Container $container = null): void
    {
        throw new Incomplete_Test_Error($this->get_action());
    }
    public function __toString(): string
    {
        return $this->get_action();
    }
}
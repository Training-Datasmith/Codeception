<?php

declare(strict_types=1);

namespace FailDependenciesInChain;

class IncorrectDependenciesClass
{
    public function _inject(AnotherClass $a)
    {
    }
}

class AnotherClass
{
    private function __construct()
    {
    }
}

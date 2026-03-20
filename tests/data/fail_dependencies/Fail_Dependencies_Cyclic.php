<?php

declare(strict_types=1);

namespace FailDependenciesCyclic;

class IncorrectDependenciesClass
{
    public function _inject(AnotherClass $a)
    {
    }
}

class AnotherClass
{
    public function _inject(IncorrectDependenciesClass $a)
    {
    }
}

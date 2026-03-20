<?php

declare(strict_types=1);

namespace FailDependenciesNonExistent;

class IncorrectDependenciesClass
{
    public function _inject(NonExistentClass $a)
    {
    }
}

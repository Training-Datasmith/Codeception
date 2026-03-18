<?php

declare(strict_types=1);

namespace Codeception\Test\Interfaces;

interface Dependent
{
    public function fetchDependencies(): array;
}

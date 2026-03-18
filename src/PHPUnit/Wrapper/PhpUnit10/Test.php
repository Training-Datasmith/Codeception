<?php

declare(strict_types=1);

namespace Codeception\PHPUnit\Wrapper;

use PHPUnit\Framework\Test as PHPUnitTest;

abstract class Test implements PHPUnitTest
{
    public function run(): void
    {
        // does nothing
    }
}

<?php

declare (strict_types=1);
namespace Codeception\Php_Unit\Wrapper;

use Php_Unit\Framework\Test as PHPUnitTest;
abstract class Test implements Php_Unit_Test
{
    public function run(): void
    {
        // does nothing
    }
}
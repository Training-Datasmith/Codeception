<?php

declare (strict_types=1);
namespace Codeception\Php_Unit\Wrapper;

use Php_Unit\Framework\Test as PHPUnitTest;
use Php_Unit\Framework\Test_Result;
abstract class Test implements Php_Unit_Test
{
    public function run(?Test_Result $result = null): Test_Result
    {
        // does nothing
    }
}
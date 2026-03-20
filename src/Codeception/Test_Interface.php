<?php

declare (strict_types=1);
namespace Codeception;

use Codeception\Test\Metadata;
use Php_Unit\Framework\Test;
interface Test_Interface extends Test
{
    public function get_metadata(): Metadata;
    public function get_result_aggregator(): Result_Aggregator;
}
<?php

declare (strict_types=1);
namespace Codeception\Lib\Console;

use Sebastian_Bergmann\Comparator\Comparison_Failure;
use Sebastian_Bergmann\Diff\Differ;
use Sebastian_Bergmann\Diff\Output\Unified_Diff_Output_Builder;
class Diff_Factory
{
    public function create_diff(Comparison_Failure $failure): string
    {
        return $this->get_diff($failure->get_expected_as_string(), $failure->get_actual_as_string());
    }
    private function get_diff(string $expected = '', string $actual = ''): string
    {
        $differ = new Differ(new Unified_Diff_Output_Builder(''));
        return $expected || $actual ? $differ->diff($expected, $actual) : '';
    }
}
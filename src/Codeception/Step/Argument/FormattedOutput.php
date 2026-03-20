<?php

declare (strict_types=1);
namespace Codeception\Step\Argument;

/**
 * Implemented in Step arguments where literal values need to be modified in test execution output (e.g. passwords).
 */
interface Formatted_Output
{
    /**
     * Returns the argument's value formatted for output.
     */
    public function get_output(): string;
    /**
     * Returns the argument's literal value.
     */
    public function __toString(): string;
}
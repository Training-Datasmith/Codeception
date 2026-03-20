<?php

declare (strict_types=1);
namespace Codeception\Step;

use Codeception\Lib\Module_Container;
use Codeception\Step as CodeceptionStep;
use Php_Unit\Framework\Skipped_Test_Error;
use Php_Unit\Framework\Skipped_With_Message_Exception;
use Php_Unit\Runner\Version as PHPUnitVersion;
class Skip extends Codeception_Step
{
    public function run(?Module_Container $container = null): void
    {
        $skip_message = $this->get_action();
        if (version_compare(Php_Unit_Version::series(), '10.0', '<') && class_exists(Skipped_Test_Error::class)) {
            throw new Skipped_Test_Error($skip_message);
        }
        throw new Skipped_With_Message_Exception($skip_message);
    }
    public function __toString(): string
    {
        return $this->get_action();
    }
}
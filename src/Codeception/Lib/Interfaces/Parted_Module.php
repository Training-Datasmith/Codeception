<?php

declare (strict_types=1);
namespace Codeception\Lib\Interfaces;

/**
 * Interface PartedModule
 *
 * Module implementing this interface can be loaded partly.
 * Parts can be defined by marking methods with `@part` annotations.
 * Part of modules can be loaded by specifying part (or several parts) in config:
 *
 * ```
 * modules:
 *      enabled: [MyModule]
 *      config:
 *          MyModule:
 *              part: usefulActions
 * ```
 *
 *
 * @package Codeception\Lib\Interfaces
 */
interface Parted_Module
{
    public function _parts(): array;
}
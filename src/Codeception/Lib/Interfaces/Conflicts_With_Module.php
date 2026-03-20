<?php

declare (strict_types=1);
namespace Codeception\Lib\Interfaces;

interface Conflicts_With_Module
{
    /**
     * Returns class name or interface of module which can conflict with current.
     */
    public function _conflicts(): string;
}
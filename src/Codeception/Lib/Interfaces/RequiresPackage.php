<?php

declare(strict_types=1);

namespace Codeception\Lib\Interfaces;

interface RequiresPackage
{
    /**
     * Returns list of classes and corresponding packages required for this module
     */
    public function _requires(): array;
}

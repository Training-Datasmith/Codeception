<?php

declare(strict_types=1);

namespace Codeception\Test\Interfaces;

interface StrictCoverage
{
    public function getLinesToBeCovered(): array|bool;

    public function getLinesToBeUsed(): array;
}

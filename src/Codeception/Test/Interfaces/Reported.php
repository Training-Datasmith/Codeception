<?php

declare(strict_types=1);

namespace Codeception\Test\Interfaces;

interface Reported
{
    /**
     * Field values for XML reports
     */
    public function getReportFields(): array;
}

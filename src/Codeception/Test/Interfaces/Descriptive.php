<?php

declare(strict_types=1);

namespace Codeception\Test\Interfaces;

use PHPUnit\Framework\SelfDescribing;

interface Descriptive extends SelfDescribing
{
    public function getFileName(): string;

    public function getSignature(): string;
}

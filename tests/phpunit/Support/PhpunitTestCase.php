<?php

declare(strict_types=1);

namespace CodeceptionPhpunit\Support;

use PHPUnit\Framework\TestCase;

abstract class PhpunitTestCase extends TestCase
{
    protected function setUp(): void
    {
        ConfigReset::capture();
        EnvReset::capture();
        AutoloadReset::capture();
        FixturesReset::capture();
    }

    protected function tearDown(): void
    {
        ConfigReset::restore();
        EnvReset::restore();
        AutoloadReset::restore();
        FixturesReset::restore();
    }
}

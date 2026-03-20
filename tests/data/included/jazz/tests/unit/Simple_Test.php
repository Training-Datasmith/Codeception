<?php

declare(strict_types=1);

namespace Jazz;

class SimpleTest extends \Codeception\Test\Unit
{
    protected UnitGuy $guy;

    public function testSimple()
    {
        $this->assertTrue(true);
    }

    public function testSimpler()
    {
        $this->assertTrue(true);
    }
}

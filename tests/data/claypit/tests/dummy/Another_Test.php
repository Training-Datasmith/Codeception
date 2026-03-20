<?php

declare(strict_types=1);

class AnotherTest extends \PHPUnit\Framework\TestCase
{
    public function testFirst()
    {
        $this->assertTrue(true);
    }

    public function testSecond()
    {
        $this->assertFalse(false);
    }
}

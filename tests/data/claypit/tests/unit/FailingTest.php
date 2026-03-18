<?php

declare(strict_types=1);

class FailingTest extends \PHPUnit\Framework\TestCase
{
    public function testMe()
    {
        $this->assertFalse(true);
    }
}

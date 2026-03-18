<?php

declare(strict_types=1);

class FailingTest extends \Codeception\Test\Unit
{
    protected UnitTester $tester;

    public function testFailing()
    {
        throw new \RuntimeException('in test');
    }
}

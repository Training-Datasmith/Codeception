<?php

declare(strict_types=1);

namespace CodeceptionPhpunit\Tests;

use Codeception\Event\FailEvent;
use Codeception\ResultAggregator;
use Codeception\Test\Cept;
use CodeceptionPhpunit\Support\PhpunitTestCase;
use Exception;

final class ResultAggregatorTest extends PhpunitTestCase
{
    public function testCountsAndStop(): void
    {
        $result = new ResultAggregator();
        $test = new Cept('n', __FILE__);
        $result->addTest($test);
        $result->addSuccessful($test);
        $result->addToAssertionCount(3);
        $this->assertSame(1, $result->testCount());
        $this->assertSame(1, $result->successfulCount());
        $this->assertSame(3, $result->assertionCount());
        $result->stop();
        $this->assertTrue($result->shouldStop());
    }

    public function testFailureCollection(): void
    {
        $result = new ResultAggregator();
        $test = new Cept('n', __FILE__);
        $result->addFailure(new FailEvent($test, new Exception('f'), 0));
        $this->assertCount(1, $result->failures());
    }
}

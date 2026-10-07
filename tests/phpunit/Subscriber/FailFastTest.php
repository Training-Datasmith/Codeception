<?php

declare(strict_types=1);

namespace CodeceptionPhpunit\Tests\Subscriber;

use Codeception\Event\FailEvent;
use Codeception\Events;
use Codeception\ResultAggregator;
use Codeception\Subscriber\FailFast;
use Codeception\Test\Cept;
use CodeceptionPhpunit\Support\PhpunitTestCase;
use Exception;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class FailFastTest extends PhpunitTestCase
{
    public function testThresholdTwoStopsAfterSecondFailure(): void
    {
        $dispatcher = new EventDispatcher();
        $result = new ResultAggregator();
        $dispatcher->addSubscriber(new FailFast(2, $result));
        $test = new Cept('t', __FILE__);

        $dispatcher->dispatch(new FailEvent($test, new Exception('one'), 0), Events::TEST_FAIL);
        $this->assertFalse($result->shouldStop());
        $dispatcher->dispatch(new FailEvent($test, new Exception('two'), 0), Events::TEST_ERROR);
        $this->assertTrue($result->shouldStop());
    }

    public function testThresholdOneStopsOnFirstFailure(): void
    {
        $dispatcher = new EventDispatcher();
        $result = new ResultAggregator();
        $dispatcher->addSubscriber(new FailFast(1, $result));
        $test = new Cept('t', __FILE__);

        $dispatcher->dispatch(new FailEvent($test, new Exception('one'), 0), Events::TEST_FAIL);
        $this->assertTrue($result->shouldStop());
    }
}

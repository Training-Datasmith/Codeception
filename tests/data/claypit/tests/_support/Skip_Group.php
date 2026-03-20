<?php

declare(strict_types=1);

use Codeception\Event\TestEvent;
use Codeception\GroupObject;

class SkipGroup extends GroupObject
{
    /** @var string */
    public static $group = 'abc';

    public function _before(TestEvent $event)
    {
        $event->getTest()->markTestSkipped('WE SKIP TEST');
    }
}

<?php

declare(strict_types=1);

namespace Unit;

use UnitTester;

/**
 * @skip
 */
class ClassLevelSkipAnnotationWithoutMessageCest
{
    public function method1(UnitTester $I)
    {
    }

    public function method2(UnitTester $I)
    {
    }
}

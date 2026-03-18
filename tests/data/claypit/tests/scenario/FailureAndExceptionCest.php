<?php

declare(strict_types=1);

class FailureAndExceptionCest
{
    public function failedTest(ScenarioGuy $I)
    {
        $I->assertSame(1, 2);
    }

    public function exceptionTest(ScenarioGuy $I)
    {
        $I->throwException('test exception');
    }
}

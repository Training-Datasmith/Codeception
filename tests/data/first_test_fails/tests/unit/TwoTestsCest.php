<?php

declare(strict_types=1);

class TwoTestsCest
{
    public function failing(UnitTester $I)
    {
        throw new \RuntimeException('error');
    }

    public function successful(UnitTester $I)
    {
        $I->assertTrue(true);
    }
}

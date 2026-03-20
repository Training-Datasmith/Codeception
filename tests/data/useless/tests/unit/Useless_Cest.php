<?php

declare(strict_types=1);

class UselessCest
{
    public function makeNoAssertions(UnitTester $I): void
    {
        $I->comment('make no assertions');
    }
}

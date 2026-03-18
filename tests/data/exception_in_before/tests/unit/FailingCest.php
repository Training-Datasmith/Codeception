<?php

declare(strict_types=1);

class FailingCest
{
    public function failing(UnitTester $I)
    {
        throw new \RuntimeException('in cest');
    }
}

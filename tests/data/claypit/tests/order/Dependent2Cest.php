<?php

declare(strict_types=1);

use Codeception\Attribute\Depends;

class Dependent2Cest
{
    #[Depends(DependentCest::class . ':firstOne')]
    public function thirdOne(OrderGuy $I)
    {
    }
}

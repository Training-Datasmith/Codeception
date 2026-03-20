<?php

declare(strict_types=1);

use Codeception\Attribute\Group;

final class AnotherCest
{
    #[Group('ok')]
    public function optimistic(DumbGuy $I)
    {
        $I->expect('everything is ok');
    }

    public function pessimistic(DumbGuy $I)
    {
        $I->expect('everything is bad');
    }
}

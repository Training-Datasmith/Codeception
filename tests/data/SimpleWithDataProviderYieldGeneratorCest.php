<?php

declare(strict_types=1);

use Codeception\Attribute\DataProvider;

class SimpleWithDataProviderYieldGeneratorCest
{
    #[DataProvider('getTestData')]
    public function helloWorld(\CodeGuy $I, \Codeception\Example $example)
    {
        $I->execute(function ($example) {
            if (!is_array($example)) {
                return false;
            }

            return count($example);
        })->seeResultEquals(2);
    }

    protected function getTestData(): Iterator
    {
        yield ['foo', 'bar'];
        yield [1, 2];
        yield [true, false];
    }
}

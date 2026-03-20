<?php

declare(strict_types=1);

use Codeception\Attribute\DataProvider;
use Codeception\Example;

class SimpleWithDataProviderArrayCest
{
    #[DataProvider('getTestData')]
    public function helloWorld(CodeGuy $I, Example $example)
    {
        $I->execute(function ($example) {
            if (!is_array($example)) {
                return false;
            }

            return count($example);
        })->seeResultEquals(2);
    }

    protected function getTestData(): array
    {
        return [
            ['foo', 'bar'],
            [1, 2],
            [true, false],
        ];
    }
}

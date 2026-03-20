<?php

declare(strict_types=1);

use Codeception\Attribute\DataProvider;
use PHPUnit\Framework\TestCase;

class DataProviderInAnotherClassTest extends TestCase
{
    #[DataProvider('AnotherClassDataProvider::getData')]
    public function testDataProvider($arg1, $arg2): void
    {
    }
}

class AnotherClassDataProvider
{
    private function getData(): array
    {
        return [
            'foo' => ['foo', 5],
            'bar' => ['bar', 6],
            'not baz' => ['baz', 7],
        ];
    }
}

<?php

declare(strict_types=1);

namespace data\data_provider;

use Codeception\Attribute\DataProvider;
use CodeGuy;
use PHPUnit\Framework\TestCase;

class DataProviderReceivesActorTest extends TestCase
{
    #[DataProvider('getData')]
    public function testDataProvider(): void
    {
    }

    public function getData(CodeGuy $I): array
    {
        return [
            $I->codeGuyMethod(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace CodeceptionPhpunit\Tests\Test;

use Codeception\Test\Descriptor;
use Codeception\Test\Interfaces\Descriptive;
use CodeceptionPhpunit\Support\PhpunitTestCase;

enum DescriptorUnitEnum
{
    case FOO;
    case BAR;
}

final class DescriptorEnumTest extends PhpunitTestCase
{
    public function testUnitEnumSignatureProperties(): void
    {
        $fooOnce = Descriptor::getTestSignatureUnique(
            $this->descriptiveWithExample(['enum' => DescriptorUnitEnum::FOO])
        );
        $fooAgain = Descriptor::getTestSignatureUnique(
            $this->descriptiveWithExample(['enum' => DescriptorUnitEnum::FOO])
        );
        $bar = Descriptor::getTestSignatureUnique(
            $this->descriptiveWithExample(['enum' => DescriptorUnitEnum::BAR])
        );

        $this->assertNotEmpty($fooOnce);
        $this->assertMatchesRegularExpression('/:[0-9a-f]{7}$/', $fooOnce);
        $this->assertNotSame($fooOnce, $bar);
        $this->assertSame($fooOnce, $fooAgain);
    }

    public function testFooUsesKnownSuffix(): void
    {
        $signature = Descriptor::getTestSignatureUnique(
            $this->descriptiveWithExample(['enum' => DescriptorUnitEnum::FOO])
        );
        $this->assertSame('sig:41e8901', $signature);
    }

    /**
     * @param array<string, mixed> $example
     */
    private function descriptiveWithExample(array $example): Descriptive
    {
        return new class ($example) implements Descriptive {
            public function __construct(private array $example)
            {
            }

            public function toString(): string
            {
                return 'wrapper';
            }

            public function getFileName(): string
            {
                return __FILE__;
            }

            public function getSignature(): string
            {
                return 'sig';
            }

            public function getMetadata(): object
            {
                return new class ($this->example) {
                    public function __construct(private array $example)
                    {
                    }

                    public function getCurrent(string $key): mixed
                    {
                        return $key === 'example' ? $this->example : null;
                    }
                };
            }
        };
    }
}

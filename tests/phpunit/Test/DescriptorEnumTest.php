<?php

declare(strict_types=1);

namespace CodeceptionPhpunit\Tests\Test;

use Codeception\Test\Descriptor;
use Codeception\Test\Interfaces\Descriptive;
use CodeceptionPhpunit\Support\PhpunitTestCase;
use UnitEnum;

enum DescriptorSampleEnum
{
    case ALPHA;
}

final class DescriptorEnumTest extends PhpunitTestCase
{
    public function testExampleMetadataHashIsDerivedFromJson(): void
    {
        $payload = ['id' => 7, 'label' => 'x'];
        $test = $this->descriptiveWithExample($payload);
        $signature = Descriptor::getTestSignatureUnique($test);
        $suffix = substr(sha1((string) json_encode($payload, JSON_THROW_ON_ERROR)), 0, 7);
        $this->assertStringEndsWith(':' . $suffix, $signature);
    }

    public function testUnitEnumUsesKnownHashSuffix(): void
    {
        $test = $this->descriptiveWithExample(['enum' => DescriptorSampleEnum::ALPHA]);
        $signature = Descriptor::getTestSignatureUnique($test);
        $this->assertSame('sig:3a2c921', $signature);
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

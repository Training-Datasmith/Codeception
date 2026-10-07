<?php

declare(strict_types=1);

namespace CodeceptionPhpunit\Tests\Util;

use Codeception\Exception\ConfigurationException;
use Codeception\Lib\ParamsLoader;
use CodeceptionPhpunit\Support\PhpunitTestCase;

final class ParamsLoaderTest extends PhpunitTestCase
{
    public function testArrayPassthrough(): void
    {
        $this->assertSame(['a' => 1], ParamsLoader::load(['a' => 1]));
    }

    public function testYamlFromDataDir(): void
    {
        $params = ParamsLoader::load('tests/data/params/params.yml');
        $this->assertSame('val1', $params['KEY1']);
    }

    public function testIniFromFixture(): void
    {
        $path = dirname(__DIR__) . '/fixtures/params.ini';
        $params = ParamsLoader::load($path);
        $this->assertSame('val1', $params['KEY1']);
        $this->assertSame('val2', $params['KEY2']);
    }

    public function testXmlTypedScalars(): void
    {
        $path = dirname(__DIR__) . '/fixtures/params_typed.xml';
        $params = ParamsLoader::load($path);
        $this->assertSame(42, $params['COUNT']);
        $this->assertSame(true, $params['FLAG']);
        $this->assertSame(3.14, $params['RATIO']);
    }

    public function testMissingFileThrows(): void
    {
        $this->expectException(ConfigurationException::class);
        ParamsLoader::load('tests/data/params/no-such-params.yml');
    }

    public function testMalformedXmlWithLibxmlGuard(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'badxml');
        file_put_contents($tmp, '<not>closed');
        $previous = libxml_use_internal_errors(true);
        try {
            $this->expectException(ConfigurationException::class);
            ParamsLoader::load($tmp);
        } finally {
            libxml_use_internal_errors($previous);
            libxml_clear_errors();
            unlink($tmp);
        }
    }
}

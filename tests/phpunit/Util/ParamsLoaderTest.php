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

    public function testIniFromDataDir(): void
    {
        $file = codecept_root_dir('tests/data/params/params.ini');
        if (!is_file($file)) {
            $this->markTestSkipped('params.ini fixture missing');
        }
        $params = ParamsLoader::load('tests/data/params/params.ini');
        $this->assertIsArray($params);
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

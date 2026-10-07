<?php

declare(strict_types=1);

namespace CodeceptionPhpunit\Tests\Util;

use Codeception\Util\Autoload;
use CodeceptionPhpunit\Support\AutoloadReset;
use CodeceptionPhpunit\Support\PhpunitTestCase;

final class AutoloadTest extends PhpunitTestCase
{
    private string $baseDir;

    private string $className;

    private string $fqcn;

    protected function setUp(): void
    {
        parent::setUp();
        $this->baseDir = sys_get_temp_dir() . '/codeception-phpunit-autoload-' . uniqid('', true);
        $this->className = 'Bar_' . bin2hex(random_bytes(4));
        $this->fqcn = 'Foo\\' . $this->className;
        mkdir($this->baseDir);
        file_put_contents(
            $this->baseDir . '/' . $this->className . '.php',
            "<?php namespace Foo; class {$this->className} {}"
        );
        Autoload::addNamespace('Foo\\', $this->baseDir);
    }

    protected function tearDown(): void
    {
        AutoloadReset::restore();
        parent::tearDown();
        $this->removeDir($this->baseDir);
    }

    public function testLoadExistingClass(): void
    {
        $path = Autoload::load($this->fqcn);
        $this->assertSame($this->baseDir . '/' . $this->className . '.php', $path);
    }

    public function testMissingClassReturnsFalse(): void
    {
        $this->assertFalse(Autoload::load('Foo\\Missing_' . bin2hex(random_bytes(2))));
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}

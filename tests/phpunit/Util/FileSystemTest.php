<?php

declare(strict_types=1);

namespace CodeceptionPhpunit\Tests\Util;

use Codeception\Util\FileSystem;
use CodeceptionPhpunit\Support\PhpunitTestCase;

final class FileSystemTest extends PhpunitTestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir() . '/cc-fs-' . uniqid('', true);
        mkdir($this->tmp);
    }

    protected function tearDown(): void
    {
        FileSystem::deleteDir($this->tmp);
        parent::tearDown();
    }

    public function testCopyAndEmptyDir(): void
    {
        $src = $this->tmp . '/src';
        $dst = $this->tmp . '/dst';
        mkdir($src);
        file_put_contents($src . '/a.txt', 'a');
        FileSystem::copyDir($src, $dst);
        $this->assertFileExists($dst . '/a.txt');
        FileSystem::doEmptyDir($dst);
        $this->assertSame([], array_diff(scandir($dst) ?: [], ['.', '..']));
    }
}

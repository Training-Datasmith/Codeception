<?php

declare(strict_types=1);

namespace CodeceptionPhpunit\Tests\Util;

use Codeception\Util\PathResolver;
use CodeceptionPhpunit\Support\PhpunitTestCase;

final class PathResolverTest extends PhpunitTestCase
{
    public function testIsPathAbsoluteUnix(): void
    {
        $this->assertTrue(PathResolver::isPathAbsolute('/tmp/foo'));
        $this->assertFalse(PathResolver::isPathAbsolute('relative/path'));
    }

    public function testGetRelativeDirSubpath(): void
    {
        $rel = PathResolver::getRelativeDir(
            '/my/proj/path/some/file.txt',
            '/my/proj/path/',
            '/'
        );
        $this->assertSame('some/file.txt', $rel);
    }
}

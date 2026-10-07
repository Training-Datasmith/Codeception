<?php

declare(strict_types=1);

namespace CodeceptionPhpunit\Tests;

use CodeceptionPhpunit\Support\PhpunitTestCase;

final class FunctionsTest extends PhpunitTestCase
{
    public function testPathHelpers(): void
    {
        $this->assertTrue(codecept_is_path_absolute('/abs'));
        $this->assertFalse(codecept_is_path_absolute('rel'));
        $root = codecept_root_dir();
        $this->assertStringEndsWith('/', $root);
        $this->assertSame($root . 'tests', codecept_absolute_path('tests'));
    }
}

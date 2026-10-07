<?php

declare(strict_types=1);

namespace CodeceptionPhpunit\Tests;

use Codeception\Example;
use CodeceptionPhpunit\Support\PhpunitTestCase;
use PHPUnit\Framework\AssertionFailedError;

final class ExampleTest extends PhpunitTestCase
{
    public function testOffsetAndCount(): void
    {
        $ex = new Example(['a' => 1, 'b' => 2]);
        $this->assertSame(1, $ex['a']);
        $this->assertCount(2, $ex);
    }

    public function testForeach(): void
    {
        $vals = [];
        foreach (new Example([1, 2]) as $v) {
            $vals[] = $v;
        }
        $this->assertSame([1, 2], $vals);
    }

    public function testMissingOffsetThrows(): void
    {
        $this->expectException(AssertionFailedError::class);
        $ex = new Example([]);
        $ex['nope'];
    }
}

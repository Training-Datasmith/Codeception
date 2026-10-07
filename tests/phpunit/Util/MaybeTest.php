<?php

declare(strict_types=1);

namespace CodeceptionPhpunit\Tests\Util;

use Codeception\Util\Maybe;
use CodeceptionPhpunit\Support\PhpunitTestCase;

final class MaybeTest extends PhpunitTestCase
{
    public function testArrayAccessAndCount(): void
    {
        $m = new Maybe(['a' => 1, 'b' => 2]);
        $this->assertSame('1', (string) $m->offsetGet('a'));
        $this->assertCount(2, iterator_to_array($m));
    }

    public function testToStringWithScalar(): void
    {
        $this->assertSame('hello', (string) new Maybe('hello'));
    }

    public function testJsonSerialize(): void
    {
        $m = new Maybe(['x' => 1]);
        $this->assertSame(['x' => 1], $m->jsonSerialize());
    }

    public function testForeach(): void
    {
        $seen = [];
        foreach (new Maybe([10, 20]) as $v) {
            $seen[] = $v;
        }
        $this->assertSame([10, 20], $seen);
    }
}

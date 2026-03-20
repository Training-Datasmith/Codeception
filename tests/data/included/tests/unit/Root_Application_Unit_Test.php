<?php

declare(strict_types=1);

namespace data\included\tests\unit;

use Codeception\Test\Unit;

class RootApplicationUnitTest extends Unit
{
    public function testFooEqualsFoo()
    {
        $this->assertSame('foo', 'foo');
    }
}

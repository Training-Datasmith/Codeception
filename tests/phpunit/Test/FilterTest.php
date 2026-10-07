<?php

declare(strict_types=1);

namespace CodeceptionPhpunit\Tests\Test;

use Codeception\Test\Cept;
use Codeception\Test\Filter;
use CodeceptionPhpunit\Support\PhpunitTestCase;

final class FilterTest extends PhpunitTestCase
{
    public function testIncludeGroup(): void
    {
        $test = new Cept('demo', __FILE__);
        $test->getMetadata()->setGroups(['fast']);
        $filter = new Filter(['fast'], null, null);
        $this->assertTrue($filter->isGroupAccepted($test, $test->getMetadata()->getGroups()));
    }

    public function testExcludeGroup(): void
    {
        $test = new Cept('demo', __FILE__);
        $test->getMetadata()->setGroups(['slow']);
        $filter = new Filter(null, ['slow'], null);
        $this->assertFalse($filter->isGroupAccepted($test, $test->getMetadata()->getGroups()));
    }
}

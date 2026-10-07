<?php

declare(strict_types=1);

namespace CodeceptionPhpunit\Tests\Configuration;

use Codeception\Configuration;
use CodeceptionPhpunit\Support\PhpunitTestCase;

final class MergeConfigsTest extends PhpunitTestCase
{
    public function testNestedMerge(): void
    {
        $a = ['modules' => ['enabled' => ['A'], 'config' => ['x' => 1]]];
        $b = ['modules' => ['enabled' => ['B'], 'config' => ['y' => 2]]];
        $merged = Configuration::mergeConfigs($a, $b);
        $this->assertSame(['B', 'A'], $merged['modules']['enabled']);
        $this->assertSame(1, $merged['modules']['config']['x']);
        $this->assertSame(2, $merged['modules']['config']['y']);
    }

    public function testNullDoesNotReplaceExistingArray(): void
    {
        $base = ['modules' => ['enabled' => []]];
        $override = ['modules' => ['enabled' => null]];
        $merged = Configuration::mergeConfigs($base, $override);
        $this->assertSame([], $merged['modules']['enabled']);
    }

    public function testListMergeUnique(): void
    {
        $merged = Configuration::mergeConfigs([1, 2], [2, 3]);
        $this->assertSame([2, 3, 1], $merged);
    }
}

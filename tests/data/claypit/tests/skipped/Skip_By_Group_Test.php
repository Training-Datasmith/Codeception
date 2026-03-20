<?php

declare(strict_types=1);

use Codeception\Attribute\Group;
use Codeception\Test\Unit;

final class SkipByGroupTest extends Unit
{
    #[Group('abc')]
    public function testSkip()
    {
        $this->assertTrue(true);
    }
}

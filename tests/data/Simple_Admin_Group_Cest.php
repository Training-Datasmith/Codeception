<?php

declare(strict_types=1);

use Codeception\Attribute\Group;

final class SimpleAdminGroupCest
{
    #[Group('admin')]
    public function testAdminGroup()
    {
    }
}

<?php

declare(strict_types=1);

namespace CodeceptionPhpunit\Tests;

use Codeception\Lib\GroupManager;
use Codeception\Test\Cept;
use CodeceptionPhpunit\Support\PhpunitTestCase;

final class GroupManagerTest extends PhpunitTestCase
{
    public function testGroupsFromArray(): void
    {
        $manager = new GroupManager([
            'important' => [
                'tests/data/group_manager_test/UserTest.php:testName',
                'tests/data/group_manager_test/PostTest.php',
            ],
        ]);
        $wrapper = new Cept('testName', codecept_root_dir() . 'tests/data/group_manager_test/UserTest.php');
        $this->assertContains('important', $manager->groupsForTest($wrapper));
    }
}

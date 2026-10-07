<?php

declare(strict_types=1);

namespace CodeceptionPhpunit\Tests\Util;

use Codeception\Util\Fixtures;
use CodeceptionPhpunit\Support\PhpunitTestCase;
use RuntimeException;

final class FixturesTest extends PhpunitTestCase
{
    public function testAddGetExists(): void
    {
        Fixtures::add('user', ['name' => 'davert']);
        $this->assertTrue(Fixtures::exists('user'));
        $this->assertSame(['name' => 'davert'], Fixtures::get('user'));
    }

    public function testMissingThrows(): void
    {
        $this->expectException(RuntimeException::class);
        Fixtures::get('missing');
    }

    public function testCleanupNamed(): void
    {
        Fixtures::add('a', 1);
        Fixtures::add('b', 2);
        Fixtures::cleanup('a');
        $this->assertFalse(Fixtures::exists('a'));
        $this->assertTrue(Fixtures::exists('b'));
    }

    public function testCleanupAll(): void
    {
        Fixtures::add('a', 1);
        Fixtures::cleanup();
        $this->assertFalse(Fixtures::exists('a'));
    }

    public function testCleanupUnknownNameDoesNotWipeOthers(): void
    {
        Fixtures::add('keep', true);
        Fixtures::cleanup('missing');
        $this->assertTrue(Fixtures::exists('keep'));
    }
}

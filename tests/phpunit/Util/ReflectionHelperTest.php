<?php

declare(strict_types=1);

namespace CodeceptionPhpunit\Tests\Util;

use Codeception\Util\ReflectionHelper;
use CodeceptionPhpunit\Support\PhpunitTestCase;
use Codeception\Util\ReflectionTestClass;

final class ReflectionHelperTest extends PhpunitTestCase
{
    public function testReadPrivateProperty(): void
    {
        $obj = new ReflectionTestClass();
        $obj->setValue('foo');
        $this->assertSame('foo', ReflectionHelper::readPrivateProperty($obj, 'value'));
    }

    public function testInvokePrivateMethod(): void
    {
        $obj = new ReflectionTestClass();
        $this->assertSame("I'm a cat!", ReflectionHelper::invokePrivateMethod($obj, 'getSecret', ['cat']));
    }
}

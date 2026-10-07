<?php

declare(strict_types=1);

namespace CodeceptionPhpunit\Tests\Test;

use Codeception\Exception\InvalidTestException;
use Codeception\Test\DataProvider;
use CodeceptionPhpunit\Support\PhpunitTestCase;
use ReflectionMethod;

final class DataProviderParseTest extends PhpunitTestCase
{
    public function testParseMethodOnly(): void
    {
        $this->assertSame(['UnitTest', 'getData'], DataProvider::parseDataProviderAnnotation('getData', 'UnitTest', 'testMethod'));
    }

    public function testParseClassAndMethod(): void
    {
        $this->assertSame(['AnotherClass', 'getData'], DataProvider::parseDataProviderAnnotation('AnotherClass::getData', 'UnitTest', 'testMethod'));
    }

    public function testInvalidAnnotationThrows(): void
    {
        $this->expectException(InvalidTestException::class);
        DataProvider::parseDataProviderAnnotation('AnotherClass::bug::getData', 'UnitTest', 'testMethod');
    }

    public function testNoProviderReturnsNull(): void
    {
        $method = new ReflectionMethod(self::class, 'testParseMethodOnly');
        $this->assertNull(DataProvider::getDataForMethod($method));
    }
}

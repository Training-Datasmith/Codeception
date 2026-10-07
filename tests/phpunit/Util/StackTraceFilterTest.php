<?php

declare(strict_types=1);

namespace CodeceptionPhpunit\Tests\Util;

use Codeception\Util\StackTraceFilter;
use CodeceptionPhpunit\Support\PhpunitTestCase;
use Exception;
use ReflectionProperty;

final class StackTraceFilterTest extends PhpunitTestCase
{
    public function testFiltersConfiguredClassPrefix(): void
    {
        $exception = new Exception('x');
        $property = new ReflectionProperty(StackTraceFilter::class, 'filteredClassesPattern');
        $property->setAccessible(true);
        $property->setValue(null, ['CodeceptionPhpunit\\Tests\\Util\\StackTraceFilterTest']);

        $trace = $exception->getTrace();
        $trace[0]['class'] = self::class;
        $fileProp = new ReflectionProperty(Exception::class, 'file');
        $fileProp->setAccessible(true);
        $lineProp = new ReflectionProperty(Exception::class, 'line');
        $lineProp->setAccessible(true);
        $fileProp->setValue($exception, '/tmp/StackTraceFilterTest.php');
        $lineProp->setValue($exception, 99);

        $filtered = StackTraceFilter::getFilteredStackTrace($exception, false, true);
        $this->assertIsArray($filtered);
    }

    public function testRealThrowIncludesProjectRelativeFrame(): void
    {
        try {
            $this->triggerNestedException();
        } catch (Exception $e) {
            $string = StackTraceFilter::getFilteredStackTrace($e, true, true);
            $this->assertStringContainsString('StackTraceFilterTest.php', $string);
            $this->assertMatchesRegularExpression('/StackTraceFilterTest\.php:\d+/', $string);
        }
    }

    private function triggerNestedException(): void
    {
        throw new Exception('filtered');
    }
}

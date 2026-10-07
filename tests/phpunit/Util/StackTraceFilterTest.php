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
        $patternProperty = new ReflectionProperty(StackTraceFilter::class, 'filteredClassesPattern');
        $patternProperty->setAccessible(true);
        $originalPatterns = $patternProperty->getValue();

        try {
            $patternProperty->setValue(null, [self::class]);

            $exception = new Exception('x');
            $traceProperty = new ReflectionProperty(Exception::class, 'trace');
            $traceProperty->setAccessible(true);
            $fileProp = new ReflectionProperty(Exception::class, 'file');
            $fileProp->setAccessible(true);
            $lineProp = new ReflectionProperty(Exception::class, 'line');
            $lineProp->setAccessible(true);

            $prependedFile = '/tmp/StackTraceFilterTest.php';
            $prependedLine = 99;
            $trace = [
                ['class' => self::class, 'file' => '/tmp/other.php', 'line' => 1],
                ['class' => 'OtherClass', 'file' => '/tmp/kept.php', 'line' => 2],
            ];
            $traceProperty->setValue($exception, $trace);
            $fileProp->setValue($exception, $prependedFile);
            $lineProp->setValue($exception, $prependedLine);

            $filtered = StackTraceFilter::getFilteredStackTrace($exception, false, true);
            $this->assertIsArray($filtered);

            foreach ($filtered as $frame) {
                if (isset($frame['class'])) {
                    $this->assertNotSame(self::class, $frame['class']);
                }
            }

            $hasPrepended = false;
            foreach ($filtered as $frame) {
                if (($frame['file'] ?? null) === $prependedFile && ($frame['line'] ?? null) === $prependedLine) {
                    $hasPrepended = true;
                    break;
                }
            }
            $this->assertTrue($hasPrepended);
        } finally {
            $patternProperty->setValue(null, $originalPatterns);
        }
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

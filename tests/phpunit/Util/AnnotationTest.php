<?php

declare(strict_types=1);

namespace CodeceptionPhpunit\Tests\Util;

use Codeception\Util\Annotation;
use CodeceptionPhpunit\Support\PhpunitTestCase;

/**
 * @author davert
 * @tag sample
 */
final class AnnotationTest extends PhpunitTestCase
{
    public function testClassAnnotation(): void
    {
        $this->assertSame('davert', Annotation::forClass(self::class)->fetch('author'));
    }

    public function testArrayValueJson(): void
    {
        $values = Annotation::arrayValue('{ "code": "200", "user": "davert" }');
        $this->assertSame(['code' => '200', 'user' => 'davert'], $values);
    }

    public function testArrayValueAnnotationStyle(): void
    {
        $values = Annotation::arrayValue('( code="200", user="davert")');
        $this->assertSame(['code' => '200', 'user' => 'davert'], $values);
    }

    public function testInvalidPrefixReturnsNull(): void
    {
        $this->assertNull(Annotation::forClass(self::class)->fetch('not-a-real-tag-xyz'));
    }
}

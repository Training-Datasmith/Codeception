<?php

declare(strict_types=1);

namespace CodeceptionPhpunit\Tests\Util;

use Codeception\Util\Template;
use CodeceptionPhpunit\Support\PhpunitTestCase;

final class TemplateTest extends PhpunitTestCase
{
    public function testPlaceholders(): void
    {
        $template = new Template('hello, {{name}}');
        $template->place('name', 'davert');
        $this->assertSame('hello, davert', $template->produce());
    }

    public function testCustomDelimiters(): void
    {
        $template = new Template('hello, %name%', '%', '%');
        $template->place('name', 'davert');
        $this->assertSame('hello, davert', $template->produce());
    }

    public function testDotNotation(): void
    {
        $template = new Template('{{user.name}}');
        $template->place('user', ['name' => 'x']);
        $this->assertSame('x', $template->produce());
    }
}

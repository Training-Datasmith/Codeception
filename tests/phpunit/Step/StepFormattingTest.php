<?php

declare(strict_types=1);

namespace CodeceptionPhpunit\Tests\Step;

use Codeception\Step\Action;
use Codeception\Step\Argument\PasswordArgument;
use Codeception\Step\ConditionalAssertion;
use CodeceptionPhpunit\Support\PhpunitTestCase;

final class StepFormattingTest extends PhpunitTestCase
{
    public function testActionString(): void
    {
        $step = new Action('click', ['#btn']);
        $this->assertStringContainsString('click', (string) $step);
    }

    public function testPasswordArgumentType(): void
    {
        $arg = new PasswordArgument('secret');
        $this->assertInstanceOf(PasswordArgument::class, $arg);
    }

    public function testConditionalAssertionActionName(): void
    {
        $step = new ConditionalAssertion('see', ['text']);
        $this->assertSame('canSee', $step->getAction());
    }
}

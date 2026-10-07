<?php

declare(strict_types=1);

namespace CodeceptionPhpunit\Tests\Console;

use Codeception\Lib\Console\Message;
use CodeceptionPhpunit\Support\PhpunitTestCase;

final class MessageTest extends PhpunitTestCase
{
    public function testCut(): void
    {
        $message = new Message('very long text');
        $this->assertSame('very long ', $message->cut(10)->getMessage());
    }

    public function testWidth(): void
    {
        $message = new Message('message example');
        $this->assertSame('message example               ', $message->width(30)->getMessage());
    }

    public function testAppendPrepend(): void
    {
        $message = new Message('core');
        $this->assertSame('>>core<<', $message->prepend('>>')->append('<<')->getMessage());
    }
}

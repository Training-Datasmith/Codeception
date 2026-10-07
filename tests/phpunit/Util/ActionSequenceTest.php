<?php

declare(strict_types=1);

namespace CodeceptionPhpunit\Tests\Util;

use Codeception\Exception\ModuleRequireException;
use Codeception\Util\ActionSequence;
use CodeceptionPhpunit\Support\PhpunitTestCase;
use PHPUnit\Framework\AssertionFailedError;
use RuntimeException;

final class ActionSequenceTest extends PhpunitTestCase
{
    public function testBuildAndRun(): void
    {
        $ctx = new class {
            public array $log = [];

            public function click(string $x): void
            {
                $this->log[] = $x;
            }
        };
        ActionSequence::build()->click('ok')->run($ctx);
        $this->assertSame(['ok'], $ctx->log);
    }

    public function testRethrowsWithStepContextForSingleArgException(): void
    {
        $ctx = new class {
            public function fail(): void
            {
                throw new RuntimeException('boom');
            }
        };
        try {
            ActionSequence::build()->fail()->run($ctx);
            $this->fail('expected exception');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('boom', $e->getMessage());
            $this->assertStringContainsString('fail', $e->getMessage());
        }
    }

    public function testPreservesModuleRequireExceptionWhenRethrowFails(): void
    {
        $ctx = new class {
            public function needModule(): void
            {
                throw new ModuleRequireException('Db', 'not configured');
            }
        };
        try {
            ActionSequence::build()->needModule()->run($ctx);
            $this->fail('expected exception');
        } catch (ModuleRequireException $e) {
            $this->assertStringContainsString('not configured', $e->getMessage());
        }
    }

    public function testFromArray(): void
    {
        $seq = ActionSequence::build()->fromArray(['see' => ['text']]);
        $this->assertCount(1, $seq->getActions());
    }
}

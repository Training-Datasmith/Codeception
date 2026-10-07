<?php

declare(strict_types=1);

namespace CodeceptionPhpunit\Tests\Subscriber;

use Codeception\Event\TestEvent;
use Codeception\Events;
use Codeception\Subscriber\Dependencies;
use Codeception\Test\Cept;
use Codeception\Test\Descriptor;
use Codeception\Test\Interfaces\Dependent;
use CodeceptionPhpunit\Support\PhpunitTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class DependenciesTest extends PhpunitTestCase
{
    public function testSkipsUntilDependencyPasses(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new Dependencies());

        $dep = new Cept('dep', __FILE__);
        $depSignature = Descriptor::getTestSignature($dep);
        $dependent = new class ('child', __FILE__, $depSignature) extends Cept implements Dependent {
            public function __construct(string $name, string $file, private string $depSignature)
            {
                parent::__construct($name, $file);
            }

            public function fetchDependencies(): array
            {
                return [$this->depSignature];
            }
        };
        $dependent->getMetadata()->setSkip('');

        $dispatcher->dispatch(new TestEvent($dependent), Events::TEST_START);
        $this->assertNotSame('', $dependent->getMetadata()->getSkip());

        $dispatcher->dispatch(new TestEvent($dep), Events::TEST_SUCCESS);
        $dependent->getMetadata()->setSkip('');
        $dispatcher->dispatch(new TestEvent($dependent), Events::TEST_START);
        $this->assertSame('', $dependent->getMetadata()->getSkip());
    }
}

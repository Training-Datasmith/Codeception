<?php

declare(strict_types=1);

use Codeception\Attribute\Group;
use PHPUnit\Framework\TestCase;

final class ExceptionTest extends TestCase
{
    #[Group('error')]
    public function testError()
    {
        throw new RuntimeException('Hello!');
    }
}

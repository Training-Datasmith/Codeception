<?php

declare(strict_types=1);

namespace CodeceptionPhpunit\Tests\Support;

use CodeceptionPhpunit\Support\EnvReset;
use PHPUnit\Framework\TestCase;

final class EnvResetTest extends TestCase
{
    private const EXISTING = 'CC_PHPUNIT_ENV_EXISTING';

    private const NEW_VAR = 'CC_PHPUNIT_ENV_NEW';

    protected function tearDown(): void
    {
        EnvReset::restore();
        putenv(self::EXISTING);
        putenv(self::NEW_VAR);
        unset($_ENV[self::EXISTING], $_SERVER[self::EXISTING], $_ENV[self::NEW_VAR], $_SERVER[self::NEW_VAR]);
        parent::tearDown();
    }

    public function testRestoresPreexistingVariable(): void
    {
        putenv(self::EXISTING . '=original');
        EnvReset::capture();
        EnvReset::putenv(self::EXISTING . '=changed');
        $this->assertSame('changed', getenv(self::EXISTING));

        EnvReset::restore();
        $this->assertSame('original', getenv(self::EXISTING));
    }

    public function testRemovesNewlyIntroducedVariable(): void
    {
        putenv(self::NEW_VAR);
        unset($_ENV[self::NEW_VAR], $_SERVER[self::NEW_VAR]);
        $this->assertFalse(getenv(self::NEW_VAR));

        EnvReset::capture();
        EnvReset::putenv(self::NEW_VAR . '=fresh');
        $this->assertSame('fresh', getenv(self::NEW_VAR));

        EnvReset::restore();
        $this->assertFalse(getenv(self::NEW_VAR));
    }
}

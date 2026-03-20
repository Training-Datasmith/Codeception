<?php

declare(strict_types=1);

/**
 * Example: A minimal Codeception unit test class.
 *
 * Run with: vendor/bin/codecept run unit
 *
 * This illustrates writing a "Cest" style test with the Unit tester actor.
 */

// This file lives at tests/unit/CalculatorCest.php in a real project.
// It is placed in examples/ here for illustration.

use Codeception\Test\Unit;

class CalculatorCest
{
    public function _before(Unit_Tester $I): void
    {
        // Runs before each test method.
    }

    /**
     * Verify that addition returns the correct sum.
     */
    public function testAddition(Unit_Tester $I): void
    {
        $calculator = new class {
            public function add(int $a, int $b): int
            {
                return $a + $b;
            }
        };

        $I->assertEquals(5, $calculator->add(2, 3));
        $I->assertGreaterThan(0, $calculator->add(1, 1));
    }

    /**
     * Verify that division by zero raises an exception.
     */
    public function testDivisionByZero(Unit_Tester $I): void
    {
        $I->expectThrowable(\DivisionByZeroError::class, function (): void {
            $result = intdiv(5, 0);
        });
    }
}

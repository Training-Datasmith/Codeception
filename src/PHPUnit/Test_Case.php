<?php

declare (strict_types=1);
namespace Codeception\Php_Unit;

/**
 * @method static void _setUpBeforeClass()
 * @method static void _tearDownAfterClass()
 */
abstract class Test_Case extends \Php_Unit\Framework\Test_Case
{
    protected function set_up(): void
    {
        if (method_exists($this, '_setUp')) {
            $this->_set_up();
        }
    }
    protected function tear_down(): void
    {
        if (method_exists($this, '_tearDown')) {
            $this->_tear_down();
        }
    }
    public static function set_up_before_class(): void
    {
        if (method_exists(static::class, '_setUpBeforeClass')) {
            static::_set_up_before_class();
        }
    }
    public static function tear_down_after_class(): void
    {
        if (method_exists(static::class, '_tearDownAfterClass')) {
            static::_tear_down_after_class();
        }
    }
    public function expect_exception_message_reg_exp(string $regular_expression): void
    {
        $this->expect_exception_message_matches($regular_expression);
    }
    public static function assert_reg_exp(string $pattern, string $string, string $message = ''): void
    {
    }
    public static function assert_not_reg_exp(string $pattern, string $string, string $message = ''): void
    {
    }
    public static function assert_file_not_exists(string $filename, string $message = ''): void
    {
    }
}
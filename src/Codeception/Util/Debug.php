<?php

declare (strict_types=1);
namespace Codeception\Util;

use Codeception\Lib\Console\Output;
use Codeception\Lib\Pause_Shell;
use Symfony\Component\Console\Helper\Question_Helper;
use Symfony\Component\Console\Input\Argv_Input;
use Symfony\Component\Console\Question\Confirmation_Question;
/**
 * This class is used only when Codeception is executed in `--debug` mode.
 * In other cases method of this class won't be seen.
 */
class Debug
{
    protected static ?Output $output = null;
    public static function set_output(Output $output): void
    {
        self::$output = $output;
    }
    /**
     * Prints data to screen. Message can be any time of data
     */
    public static function debug(mixed $message): void
    {
        self::$output?->debug($message);
    }
    public static function is_enabled(): bool
    {
        return self::$output instanceof Output;
    }
    public static function pause(array $vars = []): void
    {
        if (!self::is_enabled()) {
            return;
        }
        $pause_shell = new Pause_Shell();
        $psy = $pause_shell->get_shell();
        $psy->set_scope_variables($vars);
        foreach (debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 3) as $backtrace_step) {
            $class = $backtrace_step['class'] ?? null;
            $fn = $backtrace_step['function'] ?? null;
            if ($class === self::class && $fn === 'pause') {
                continue;
            }
            if ($fn === 'codecept_pause' && !$class) {
                continue;
            }
            if (!isset($backtrace_step['object'])) {
                continue;
            }
            $pause_shell->add_message('Use $this-> to access current object');
            $psy->set_bound_object($backtrace_step['object']);
            break;
        }
        $psy->run();
    }
    public static function confirm($question)
    {
        if (!self::$output instanceof Output) {
            return null;
        }
        return (new Question_Helper())->ask(new Argv_Input(), self::$output, new Confirmation_Question($question));
    }
}
<?php

declare (strict_types=1);
namespace Codeception\Subscriber;

use function call_user_func;
use function class_exists;
use Codeception\Event\Suite_Event;
use Codeception\Events;
use Codeception\Exception\Deprecation;
use Codeception\Exception\Error;
use Codeception\Exception\Notice;
use Codeception\Exception\Warning;
use Codeception\Lib\Notification;
use function error_get_last;
use function error_reporting;
use function getenv;
use function in_array;
use function is_array;
use const PHP_VERSION_ID;
use Php_Unit\Runner\Version as PHPUnitVersion;
use function register_shutdown_function;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;
use Symfony\Bridge\Php_Unit\Deprecation_Error_Handler as SymfonyDeprecationErrorHandler;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
class Error_Handler implements Event_Subscriber_Interface
{
    use Shared\Static_Events_Trait;
    /**
     * @var array<string, string>
     */
    protected static array $events = [Events::SUITE_BEFORE => 'handle', Events::SUITE_AFTER => 'onFinish'];
    /**
     * @var bool $stopped to keep shutdownHandler from possible looping.
     */
    private bool $stopped = false;
    /**
     * @var bool $initialized to avoid double error handler substitution
     */
    private bool $initialized = false;
    private bool $deprecations_installed = false;
    /**
     * @var callable|null
     */
    private $old_handler;
    private bool $suite_finished = false;
    /**
     * @var int Stores bitmask for errors
     */
    private int $error_level;
    private bool $convert_deprecations_to_exceptions = false;
    public function __construct()
    {
        // E_STRICT is deprecated in PHP 8.4
        $this->error_level = PHP_VERSION_ID < 80400 ? E_ALL & ~E_STRICT & ~E_DEPRECATED : E_ALL & ~E_DEPRECATED;
    }
    public function on_finish(Suite_Event $event): void
    {
        $this->suite_finished = true;
    }
    public function handle(Suite_Event $event): void
    {
        $settings = $event->get_settings();
        if ($settings['error_level']) {
            $this->error_level = eval("return {$settings['error_level']};");
        }
        error_reporting($this->error_level);
        if ($settings['convert_deprecations_to_exceptions']) {
            $this->convert_deprecations_to_exceptions = true;
        }
        if ($this->initialized) {
            return;
        }
        // We must register shutdown function before deprecation error handler to restore previous error handler
        // and silence DeprecationErrorHandler yelling about 'THE ERROR HANDLER HAS CHANGED!'
        register_shutdown_function([$this, 'shutdownHandler']);
        $this->register_deprecation_error_handler();
        $this->old_handler = set_error_handler($this->error_handler(...));
        $this->initialized = true;
    }
    public function error_handler(int $err_num, string $err_msg, string $err_file, int $err_line, array $context = []): bool
    {
        if ((E_USER_DEPRECATED === $err_num || E_DEPRECATED === $err_num) && !$this->convert_deprecations_to_exceptions) {
            $this->handle_deprecation_error($err_num, $err_msg, $err_file, $err_line, $context);
            return true;
        }
        if ((error_reporting() & $err_num) === 0) {
            // This error code is not included in error_reporting
            return false;
        }
        if (str_contains($err_msg, 'Cannot modify header information')) {
            return false;
        }
        if (version_compare(Php_Unit_Version::series(), '10.0', '<')) {
            $map = [E_DEPRECATED => 'PHPUnit\Framework\Error\Deprecated', E_USER_DEPRECATED => 'PHPUnit\Framework\Error\Deprecated', E_NOTICE => 'PHPUnit\Framework\Error\Notice', E_USER_NOTICE => 'PHPUnit\Framework\Error\Notice', E_WARNING => 'PHPUnit\Framework\Error\Warning', E_USER_WARNING => 'PHPUnit\Framework\Error\Warning'];
            $class_name = $map[$err_num] ?? 'PHPUnit\Framework\Error\Error';
            if (class_exists($class_name)) {
                throw new $class_name($err_msg, $err_num, $err_file, $err_line);
            }
        }
        $err_msg_with_location = $err_msg . ' at ' . $err_file . ':' . $err_line;
        throw match ($err_num) {
            E_DEPRECATED, E_USER_DEPRECATED => new Deprecation($err_msg_with_location, $err_num, $err_file, $err_line),
            E_NOTICE, E_USER_NOTICE => new Notice($err_msg_with_location, $err_num, $err_file, $err_line),
            E_WARNING, E_USER_WARNING => new Warning($err_msg_with_location, $err_num, $err_file, $err_line),
            default => new Error($err_msg_with_location, $err_num, $err_file, $err_line),
        };
    }
    public function shutdown_handler(): void
    {
        if ($this->deprecations_installed) {
            restore_error_handler();
        }
        if ($this->stopped) {
            return;
        }
        $this->stopped = true;
        $error = error_get_last();
        if (!$this->suite_finished && ($error === null || !in_array($error['type'], [E_ERROR, E_COMPILE_ERROR, E_CORE_ERROR]))) {
            echo "\n\n\nCOMMAND DID NOT FINISH PROPERLY.\n";
            exit(125);
        }
        if (!is_array($error)) {
            return;
        }
        if (error_reporting() === 0) {
            return;
        }
        // not fatal
        if (!in_array($error['type'], [E_ERROR, E_COMPILE_ERROR, E_CORE_ERROR])) {
            return;
        }
        echo "\n\n\nFATAL ERROR. TESTS NOT FINISHED.\n";
        echo sprintf("%s \nin %s:%d\n", $error['message'], $error['file'], $error['line']);
    }
    private function register_deprecation_error_handler(): void
    {
        if (class_exists('\Symfony\Bridge\PhpUnit\DeprecationErrorHandler') && 'disabled' !== getenv('SYMFONY_DEPRECATIONS_HELPER')) {
            // DeprecationErrorHandler only will be installed if array('PHPUnit\Util\ErrorHandler', 'handleError')
            // is installed or no other error handlers are installed.
            // So we will remove Symfony\Component\ErrorHandler\ErrorHandler if it's installed.
            $old = set_error_handler(var_dump(...));
            restore_error_handler();
            if ($old && is_array($old) && $old !== [] && $old[0] instanceof \Symfony\Component\Error_Handler\Error_Handler) {
                restore_error_handler();
            }
            $this->deprecations_installed = true;
            Symfony_Deprecation_Error_Handler::register(getenv('SYMFONY_DEPRECATIONS_HELPER'));
        }
    }
    private function handle_deprecation_error(int $type, string $message, string $file, int $line, array $context): void
    {
        if (($this->error_level & $type) === 0) {
            return;
        }
        if ($this->deprecations_installed && $this->old_handler) {
            call_user_func($this->old_handler, $type, $message, $file, $line, $context);
            return;
        }
        Notification::deprecate("{$message}", "{$file}:{$line}");
    }
}
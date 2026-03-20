<?php

declare (strict_types=1);
namespace Codeception;

use Codeception\Exception\Module_Config_Exception;
use Codeception\Exception\Module_Exception;
use Codeception\Lib\Interfaces\Requires_Package;
use Codeception\Lib\Module_Container;
use Codeception\Util\Shared\Asserts;
use Exception;
/**
 * Basic class for Modules and Helpers.
 * You must extend from it while implementing own helpers.
 *
 * Public methods of this class start with `_` prefix in order to ignore them in actor classes.
 * Module contains **HOOKS** which allow to handle test execution routine.
 *
 */
abstract class Module
{
    use Asserts;
    /**
     * By setting it to false module wan't inherit methods of parent class.
     */
    public static bool $include_inherited_actions = true;
    /**
     * Allows to explicitly set what methods have this class.
     */
    public static array $only_actions = [];
    /**
     * Allows to explicitly exclude actions from module.
     */
    public static array $exclude_actions = [];
    /**
     * Allows to rename actions
     */
    public static array $aliases = [];
    protected array $storage = [];
    protected array $config = [];
    protected array $backup_config = [];
    protected array $required_fields = [];
    /**
     * Module constructor.
     *
     * Requires module container (to provide access between modules of suite) and config.
     */
    public function __construct(protected Module_Container $module_container, ?array $config = null)
    {
        $this->backup_config = $this->config;
        if (is_array($config)) {
            $this->_set_config($config);
        }
    }
    /**
     * Allows to define initial module config.
     * Can be used in `_beforeSuite` hook of Helpers or Extensions
     *
     * ```php
     * <?php
     * public function _beforeSuite($settings = []) {
     *     $this->getModule('otherModule')->_setConfig($this->myOtherConfig);
     * }
     * ```
     *
     * @throws ModuleConfigException|ModuleException
     */
    public function _set_config(array $config): void
    {
        $this->config = array_merge($this->config, $config);
        $this->backup_config = $this->config;
        $this->validate_config();
    }
    /**
     * Allows to redefine config for a specific test.
     * Config is restored at the end of a test.
     *
     * ```php
     * <?php
     * // cleanup DB only for specific group of tests
     * public function _before(Test $test) {
     *     if (in_array('cleanup', $test->getMetadata()->getGroups()) {
     *         $this->getModule('Db')->_reconfigure(['cleanup' => true]);
     *     }
     * }
     * ```
     *
     * @throws ModuleConfigException|ModuleException
     */
    public function _reconfigure(array $config): void
    {
        $this->config = array_merge($this->backup_config, $config);
        $this->on_reconfigure();
        $this->validate_config();
    }
    /**
     * HOOK to be executed when config changes with `_reconfigure`.
     */
    protected function on_reconfigure()
    {
        // update client on reconfigurations
    }
    /**
     * Reverts config changed by `_reconfigure`
     */
    public function _reset_config(): void
    {
        $this->config = $this->backup_config;
    }
    /**
     * Validates current config for required fields and required packages.
     *
     * @throws ModuleConfigException|ModuleException
     */
    protected function validate_config(): void
    {
        if (($missing = array_diff($this->required_fields, array_keys($this->config))) !== []) {
            throw new Module_Config_Exception(static::class, sprintf("\nOptions: %s are required\nPlease, update the configuration and set all the required fields\n\n", implode(', ', $missing)));
        }
        if ($this instanceof Requires_Package) {
            $errors = '';
            foreach ($this->_requires() as $class_name => $package) {
                if (!class_exists($class_name)) {
                    $errors .= "Class {$class_name} can't be loaded, please add {$package} to composer.json\n";
                }
            }
            if ($errors !== '') {
                throw new Module_Exception($this, $errors);
            }
        }
    }
    /**
     * Returns a module name for a Module, a class name for Helper
     */
    public function _get_name(): string
    {
        $module_name = '\\' . static::class;
        return str_starts_with($module_name, Module_Container::MODULE_NAMESPACE) ? substr($module_name, strlen(Module_Container::MODULE_NAMESPACE)) : $module_name;
    }
    /**
     * Checks if a module has required fields
     */
    public function _has_required_fields(): bool
    {
        return $this->required_fields !== [];
    }
    /**
     * **HOOK** triggered after module is created and configuration is loaded
     */
    public function _initialize()
    {
    }
    /**
     * **HOOK** executed before suite
     */
    public function _before_suite(array $settings = [])
    {
    }
    /**
     * **HOOK** executed after suite
     */
    public function _after_suite()
    {
    }
    /**
     * **HOOK** executed before step
     */
    public function _before_step(Step $step)
    {
    }
    /**
     * **HOOK** executed after step
     */
    public function _after_step(Step $step)
    {
    }
    /**
     * **HOOK** executed before test
     */
    public function _before(Test_Interface $test)
    {
    }
    /**
     * **HOOK** executed after test
     */
    public function _after(Test_Interface $test)
    {
    }
    /**
     * **HOOK** executed when test fails but before `_after`
     */
    public function _failed(Test_Interface $test, Exception $fail)
    {
    }
    /**
     * Print debug message to the screen.
     */
    protected function debug(mixed $message): void
    {
        codecept_debug($message);
    }
    /**
     * Print debug message with a title
     */
    protected function debug_section(string $title, mixed $msg): void
    {
        if (is_array($msg) || is_object($msg)) {
            $msg = json_encode($msg, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES);
        }
        $this->debug("[{$title}] {$msg}");
    }
    /**
     * Short text message to an amount of chars
     */
    protected function shorten_message(string $message, int $chars = 150): string
    {
        return mb_substr($message, 0, $chars, 'utf-8');
    }
    /**
     * Checks that module is enabled.
     */
    protected function has_module(string $name): bool
    {
        return $this->module_container->has_module($name);
    }
    /**
     * Get all enabled modules
     */
    protected function get_modules(): array
    {
        return $this->module_container->all();
    }
    /**
     * Get another module by its name:
     *
     * ```php
     * <?php
     * $this->getModule('WebDriver')->_findElements('.items');
     * ```
     *
     * @throws ModuleException
     */
    protected function get_module(string $name): Module
    {
        if (!$this->has_module($name)) {
            $this->module_container->throw_missing_module_exception_with_suggestion(self::class, $name);
        }
        return $this->module_container->get_module($name);
    }
    /**
     * Get config values or specific config item.
     *
     * @return mixed the config item's value or null if it doesn't exist
     */
    public function _get_config(?string $key = null): mixed
    {
        return $key === null ? $this->config : $this->config[$key] ?? null;
    }
    protected function scalarize_array(array $array): array
    {
        array_walk_recursive($array, static function (&$value): void {
            if (!is_null($value) && !is_scalar($value)) {
                $value = (string) $value;
            }
        });
        return $array;
    }
}
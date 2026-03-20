<?php

declare (strict_types=1);
namespace Codeception\Lib;

use Codeception\Configuration;
use Codeception\Exception\Configuration_Exception;
use Codeception\Exception\Injection_Exception;
use Codeception\Exception\Module_Conflict_Exception;
use Codeception\Exception\Module_Exception;
use Codeception\Exception\Module_Require_Exception;
use Codeception\Lib\Interfaces\Conflicts_With_Module;
use Codeception\Lib\Interfaces\Depends_On_Module;
use Codeception\Lib\Interfaces\Parted_Module;
use Codeception\Module;
use Codeception\Util\Annotation;
use ReflectionClass;
use Reflection_Exception;
use ReflectionMethod;
/**
 * Class ModuleContainer
 * @package Codeception\Lib
 */
class Module_Container
{
    /**
     * @var string
     */
    public const MODULE_NAMESPACE = '\Codeception\Module\\';
    /**
     * @var int
     */
    public const MAXIMUM_LEVENSHTEIN_DISTANCE = 5;
    /**
     * @var array<string, string>
     */
    public static array $packages = ['AMQP' => 'codeception/module-amqp', 'Apc' => 'codeception/module-apc', 'Asserts' => 'codeception/module-asserts', 'Cli' => 'codeception/module-cli', 'DataFactory' => 'codeception/module-datafactory', 'Db' => 'codeception/module-db', 'Doctrine' => 'codeception/module-doctrine', 'Filesystem' => 'codeception/module-filesystem', 'FTP' => 'codeception/module-ftp', 'Laravel' => 'codeception/module-laravel', 'Lumen' => 'codeception/module-lumen', 'Memcache' => 'codeception/module-memcache', 'MongoDb' => 'codeception/module-mongodb', 'Phalcon' => 'codeception/module-phalcon', 'PhpBrowser' => 'codeception/module-phpbrowser', 'Queue' => 'codeception/module-queue', 'Redis' => 'codeception/module-redis', 'REST' => 'codeception/module-rest', 'Sequence' => 'codeception/module-sequence', 'SOAP' => 'codeception/module-soap', 'Symfony' => 'codeception/module-symfony', 'WebDriver' => 'codeception/module-webdriver', 'Yii2' => 'codeception/module-yii2', 'ZendExpressive' => 'codeception/module-zendexpressive', 'ZF2' => 'codeception/module-zf2'];
    /**
     * @var array<string,Module>
     */
    private array $modules = [];
    private array $active = [];
    private array $actions = [];
    public function __construct(private readonly Di $di, private array $config)
    {
        $this->di->set($this);
    }
    /**
     * Create a module.
     *
     * @throws ConfigurationException
     * @throws InjectionException
     * @throws ModuleException
     * @throws ModuleRequireException
     * @throws ReflectionException
     */
    public function create(string $module_name, bool $active = true): ?object
    {
        $this->active[$module_name] = $active;
        $module_class = $this->get_module_class($module_name);
        if (!class_exists($module_class)) {
            if (isset(self::$packages[$module_name])) {
                $package = self::$packages[$module_name];
                throw new Configuration_Exception("Codeception's module {$module_name} not found. Install it with:\n\ncomposer require {$package} --dev");
            }
            throw new Configuration_Exception("Module {$module_name} could not be found and loaded");
        }
        $config = $this->get_module_config($module_name);
        if ($config === [] && !$active) {
            // For modules that are a dependency of other modules we want to skip the validation of the config.
            // This config validation is performed in \Codeception\Module::__construct().
            // Explicitly setting $config to null skips this validation.
            $config = null;
        }
        $this->modules[$module_name] = $this->di->instantiate($module_class, [$this, $config], 'false');
        $module = $this->modules[$module_name];
        if ($this->module_has_dependencies($module)) {
            $this->inject_module_dependencies($module_name, $module);
        }
        // If module is not active its actions should not be included in the actor class
        $actions = $active ? $this->get_actions_for_module($module, $config) : [];
        foreach ($actions as $action) {
            $this->actions[$action] = $module_name;
        }
        return $module;
    }
    /**
     * Does a module have dependencies?
     */
    private function module_has_dependencies(Module $module): bool
    {
        if (!$module instanceof Depends_On_Module) {
            return false;
        }
        return (bool) $module->_depends();
    }
    /**
     * Get the actions of a module.
     *
     * @return string[]
     */
    private function get_actions_for_module(Module $module, array $config): array
    {
        $reflection_class = new ReflectionClass($module);
        // Only public methods can be actions
        $methods = $reflection_class->get_methods(ReflectionMethod::IS_PUBLIC);
        // Should this module be loaded partially?
        $configured_parts = null;
        if ($module instanceof Parted_Module && isset($config['part'])) {
            $configured_parts = is_array($config['part']) ? $config['part'] : [$config['part']];
        }
        $actions = [];
        foreach ($methods as $method) {
            if ($this->include_method_as_action($module, $method, $configured_parts)) {
                $actions[] = $method->name;
            }
        }
        return $actions;
    }
    /**
     * Should a method be included as an action?
     */
    private function include_method_as_action(Module $module, ReflectionMethod $method, ?array $configured_parts = null): bool
    {
        // Filter out excluded actions
        if ($module::$exclude_actions && in_array($method->name, $module::$exclude_actions)) {
            return false;
        }
        // Keep only the $onlyActions if they are specified
        if ($module::$only_actions && !in_array($method->name, $module::$only_actions)) {
            return false;
        }
        // Do not include inherited actions if the static $includeInheritedActions property is set to false.
        // However, if an inherited action is also specified in the static $onlyActions property
        // it should be included as an action.
        if (!$module::$include_inherited_actions && !in_array($method->name, $module::$only_actions) && $method->get_declaring_class()->get_name() !== $module::class) {
            return false;
        }
        // Do not include hidden methods, methods with a name starting with an underscore
        if (str_starts_with($method->name, '_')) {
            return false;
        }
        // If a part is configured for the module, only include actions from that part
        if ($configured_parts) {
            $module_parts = Annotation::for_method($module, $method->name)->fetch_all('part');
            if (array_uintersect($module_parts, $configured_parts, 'strcasecmp') === []) {
                return false;
            }
        }
        return true;
    }
    /**
     * Is the module a helper?
     */
    private function is_helper(string $module_name): bool
    {
        return str_contains($module_name, '\\');
    }
    /**
     * Get the fully qualified class name for a module.
     */
    private function get_module_class(string $module_name): string
    {
        if ($this->is_helper($module_name)) {
            return $module_name;
        }
        return self::MODULE_NAMESPACE . $module_name;
    }
    /**
     * Is a module instantiated in this ModuleContainer?
     */
    public function has_module(string $module_name): bool
    {
        return isset($this->modules[$module_name]);
    }
    /**
     * Get a module from this ModuleContainer.
     *
     * @throws ModuleException
     */
    public function get_module(string $module_name): Module
    {
        if (!$this->has_module($module_name)) {
            $this->throw_missing_module_exception_with_suggestion(self::class, $module_name);
        }
        return $this->modules[$module_name];
    }
    public function throw_missing_module_exception_with_suggestion(string $class_name, string $module_name): void
    {
        $suggested_module_name_info = $this->get_module_suggestion($module_name);
        throw new Module_Exception($class_name, "Module {$module_name} couldn't be connected" . $suggested_module_name_info);
    }
    protected function get_module_suggestion(string $missing_module_name): string
    {
        $shortest_levenshtein_distance = null;
        $suggested_module_name = null;
        foreach (array_keys($this->modules) as $module_name) {
            $levenshtein_distance = levenshtein($missing_module_name, $module_name);
            if ($shortest_levenshtein_distance === null || $levenshtein_distance <= $shortest_levenshtein_distance) {
                $shortest_levenshtein_distance = $levenshtein_distance;
                $suggested_module_name = $module_name;
            }
        }
        if ($suggested_module_name !== null && $shortest_levenshtein_distance <= self::MAXIMUM_LEVENSHTEIN_DISTANCE) {
            return " (did you mean '{$suggested_module_name}'?)";
        }
        return '';
    }
    /**
     * Get the module for an action.
     *
     * @return Module|null
     */
    public function module_for_action(string $action)
    {
        if (!isset($this->actions[$action])) {
            return null;
        }
        return $this->modules[$this->actions[$action]];
    }
    /**
     * Get all actions.
     *
     * @return array An array with actions as keys and module names as values.
     */
    public function get_actions(): array
    {
        return $this->actions;
    }
    /**
     * Get all modules.
     *
     * @return array An array with module names as keys and modules as values.
     */
    public function all(): array
    {
        return $this->modules;
    }
    /**
     * Mock a module in this ModuleContainer.
     */
    public function mock(string $module_name, object $mock): void
    {
        $this->modules[$module_name] = $mock;
    }
    /**
     * Inject the dependencies of a module.
     *
     * @throws ModuleException
     * @throws ModuleRequireException
     */
    private function inject_module_dependencies(string $module_name, Depends_On_Module $module): void
    {
        $this->check_for_missing_dependencies($module_name, $module);
        if (!method_exists($module, '_inject')) {
            throw new Module_Exception($module, 'Module requires method _inject to be defined to accept dependencies');
        }
        $dependencies = array_map(fn(string $dependency): ?object => $this->create($dependency, false), $this->get_configured_dependencies($module_name));
        call_user_func_array([$module, '_inject'], $dependencies);
    }
    /**
     * Check for missing dependencies.
     *
     * @throws ModuleException|ModuleRequireException
     */
    private function check_for_missing_dependencies(string $module_name, Depends_On_Module $module): void
    {
        $dependencies = $this->get_module_dependencies($module);
        $configured_dependencies_count = count($this->get_configured_dependencies($module_name));
        if ($configured_dependencies_count < count($dependencies)) {
            $missing_dependency = array_keys($dependencies)[$configured_dependencies_count];
            $message = sprintf("\nThis module depends on %s\n\n\n%s", $missing_dependency, $this->get_error_message_for_dependency($module, $missing_dependency));
            throw new Module_Require_Exception($module_name, $message);
        }
    }
    /**
     * Get the dependencies of a module.
     *
     * @throws ModuleException
     */
    private function get_module_dependencies(Depends_On_Module $module): array
    {
        $depends = $module->_depends();
        if ($depends === []) {
            return [];
        }
        if (!is_array($depends)) {
            $message = sprintf("Method _depends of module '%s' must return an array", $module::class);
            throw new Module_Exception($module, $message);
        }
        return $depends;
    }
    /**
     * Get the configured dependencies for a module.
     */
    private function get_configured_dependencies(string $module_name): array
    {
        $config = $this->get_module_config($module_name);
        if (!isset($config['depends'])) {
            return [];
        }
        return is_array($config['depends']) ? $config['depends'] : [$config['depends']];
    }
    /**
     * Get the error message for a module dependency that is missing.
     */
    private function get_error_message_for_dependency(Depends_On_Module $module, string $missing_dependency): string
    {
        $depends = $module->_depends();
        return $depends[$missing_dependency];
    }
    /**
     * Get the configuration for a module.
     *
     * A module with name $moduleName can be configured at two paths in a configuration file:
     * - modules.config.$moduleName
     * - modules.enabled.$moduleName
     *
     * This method checks both locations for configuration. If there is configuration at both locations
     * this method merges them, where the configuration at modules.enabled.$moduleName takes precedence
     * over modules.config.$moduleName if the same parameters are configured at both locations.
     */
    private function get_module_config(string $module_name): array
    {
        $config = $this->config['modules']['config'][$module_name] ?? [];
        if (!isset($this->config['modules']['enabled'])) {
            return $config;
        }
        if (!is_array($this->config['modules']['enabled'])) {
            return $config;
        }
        foreach ($this->config['modules']['enabled'] as $enabled_module_config) {
            if (!is_array($enabled_module_config)) {
                continue;
            }
            $enabled_module_name = key($enabled_module_config);
            if ($enabled_module_name === $module_name) {
                $module_config = reset($enabled_module_config);
                if (!is_array($module_config)) {
                    return $config;
                }
                return Configuration::merge_configs($module_config, $config);
            }
        }
        return $config;
    }
    /**
     * Check if there are conflicting modules in this ModuleContainer.
     *
     * @throws ModuleConflictException
     */
    public function validate_conflicts(): void
    {
        $can_conflict = [];
        foreach ($this->modules as $module_name => $module) {
            $parted = $module instanceof Parted_Module && $module->_get_config('part');
            if ($this->active[$module_name] && !$parted) {
                $can_conflict[] = $module;
            }
        }
        foreach ($can_conflict as $module) {
            foreach ($can_conflict as $other_module) {
                $this->validate_conflict($module, $other_module);
            }
        }
    }
    /**
     * Check if the modules passed as arguments to this method conflict with each other.
     *
     * @throws ModuleConflictException
     */
    private function validate_conflict(Module $module, Module $other_module): void
    {
        if ($module === $other_module || !$module instanceof Conflicts_With_Module) {
            return;
        }
        $conflicts = $this->normalize_conflict_specification($module->_conflicts());
        if ($other_module instanceof $conflicts) {
            throw new Module_Conflict_Exception($module, $other_module);
        }
    }
    /**
     * Normalize the return value of ConflictsWithModule::_conflicts() to a class name.
     * This is necessary because it can return a module name instead of the name of a class or interface.
     *
     * @return class-string|Module|string
     */
    private function normalize_conflict_specification(string $conflicts): string|Module
    {
        if (interface_exists($conflicts) || class_exists($conflicts)) {
            return $conflicts;
        }
        if ($this->has_module($conflicts)) {
            return $this->get_module($conflicts);
        }
        return $conflicts;
    }
}
<?php

declare (strict_types=1);
namespace Codeception;

use function array_unique;
use Codeception\Exception\Configuration_Exception;
use Codeception\Lib\Params_Loader;
use Codeception\Step\Conditional_Assertion;
use Codeception\Util\Autoload;
use Codeception\Util\Path_Resolver;
use Codeception\Util\Template;
use InvalidArgumentException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Exception\Parse_Exception;
use Symfony\Component\Yaml\Yaml;
class Configuration
{
    /**
     * @var string[]
     */
    protected static array $suites = [];
    /**
     * @var array<string, mixed>|null Current configuration
     */
    protected static ?array $config = null;
    /**
     * @var array environmental files configuration cache
     */
    protected static array $env_config = [];
    /**
     * @var string|null Directory containing main configuration file.
     * @see self::projectDir()
     */
    protected static ?string $dir = null;
    /**
     * @var string|null Directory of a base configuration file for the project with includes.
     * @see self::projectDir()
     */
    protected static ?string $base_dir = null;
    /**
     * @var string|null Current project output directory.
     */
    protected static ?string $output_dir = null;
    /**
     * @var string|null Current project data directory. This directory is used to hold
     * sql dumps and other things needed for current project tests.
     */
    protected static ?string $data_dir = null;
    /**
     * @var string|null Directory with test support files like Actors, Helpers, PageObjects, etc
     */
    protected static ?string $support_dir = null;
    /**
     * @var string|null Directory containing environment configuration files.
     */
    protected static ?string $envs_dir = null;
    /**
     * @var string|null Directory containing tests and suites of the current project.
     */
    protected static ?string $tests_dir = null;
    public static bool $lock = false;
    /**
     * @var array<string, mixed>|null
     */
    protected static ?array $params = null;
    /**
     * @var array<string, mixed>
     */
    public static array $default_config = ['actor_suffix' => 'Tester', 'support_namespace' => null, 'namespace' => '', 'include' => [], 'paths' => [], 'extends' => null, 'suites' => [], 'modules' => [], 'extensions' => ['enabled' => [], 'config' => [], 'commands' => []], 'groups' => [], 'bootstrap' => false, 'settings' => ['colors' => true, 'bootstrap' => false, 'strict_xml' => false, 'lint' => true, 'backup_globals' => true, 'report_useless_tests' => false, 'be_strict_about_changes_to_global_state' => false, 'shuffle' => false], 'coverage' => [], 'params' => [], 'gherkin' => []];
    /**
     * @var array<string, mixed>
     */
    public static array $default_suite_settings = ['actor' => null, 'modules' => ['enabled' => [], 'config' => [], 'depends' => []], 'step_decorators' => Conditional_Assertion::class, 'path' => null, 'extends' => null, 'namespace' => null, 'groups' => [], 'formats' => [], 'shuffle' => false, 'extensions' => ['enabled' => [], 'config' => []], 'error_level' => 'E_ALL & ~E_DEPRECATED', 'convert_deprecations_to_exceptions' => false];
    /**
     * Loads global config file which is `codeception.yml` by default.
     * When config is already loaded - returns it.
     *
     * @return array<string, mixed>
     * @throws ConfigurationException
     */
    public static function config(?string $config_file = null): array
    {
        if (!$config_file && self::$config) {
            return self::$config;
        }
        if (self::$config && self::$lock) {
            return self::$config;
        }
        if ($config_file === null) {
            $config_file = getcwd() . DIRECTORY_SEPARATOR . 'codeception.yml';
        }
        if (is_dir($config_file)) {
            $config_file = rtrim($config_file, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'codeception.yml';
        }
        $dir = realpath(dirname($config_file));
        if ($dir !== false) {
            self::$dir = $dir;
            self::$base_dir ??= $dir;
        }
        $config_dist_file = ($dir !== false ? $dir : dirname($config_file)) . DIRECTORY_SEPARATOR . 'codeception.dist.yml';
        if (!file_exists($config_file) && !file_exists($config_dist_file)) {
            throw new Configuration_Exception("Configuration file could not be found.\nRun bootstrap to initialize Codeception.", 404);
        }
        $temp_config = self::$default_config;
        $dist_config_contents = '';
        if (file_exists($config_dist_file)) {
            $dist_config_contents = file_get_contents($config_dist_file);
            if ($dist_config_contents === false) {
                throw new Configuration_Exception("Failed to read {$config_dist_file}");
            }
            $temp_config = self::merge_configs($temp_config, self::get_conf_from_contents($dist_config_contents, $config_dist_file));
        }
        $config_contents = '';
        if (file_exists($config_file)) {
            $config_contents = file_get_contents($config_file);
            if ($config_contents === false) {
                throw new Configuration_Exception("Failed to read {$config_file}");
            }
            $temp_config = self::merge_configs($temp_config, self::get_conf_from_contents($config_contents, $config_file));
        }
        self::prepare_params($temp_config);
        $config = self::$default_config;
        if ($dist_config_contents !== '') {
            $config = self::merge_configs($config, self::get_conf_from_contents($dist_config_contents, $config_dist_file));
        }
        if ($config_contents !== '') {
            $config = self::merge_configs($config, self::get_conf_from_contents($config_contents, $config_file));
        }
        if ($config === self::$default_config) {
            throw new Configuration_Exception('Configuration file is invalid');
        }
        if (isset($config['extends'])) {
            $preset_file_path = codecept_absolute_path($config['extends']);
            if (file_exists($preset_file_path)) {
                $config = self::merge_configs(self::get_conf_from_file($preset_file_path), $config);
            }
        }
        self::$config = $config;
        if (!isset(self::$config['paths']['support']) && isset(self::$config['paths']['helpers'])) {
            self::$config['paths']['support'] = self::$config['paths']['helpers'];
        }
        if (!isset(self::$config['paths']['output'])) {
            throw new Configuration_Exception('Output path is not defined by key "paths: output"');
        }
        self::$output_dir = self::$config['paths']['output'];
        self::$config['include'] = self::expand_wildcarded_includes(self::$config['include']);
        if (!empty(self::$config['include']) && !isset(self::$config['paths']['tests'])) {
            return self::$config;
        }
        self::validate_paths();
        self::load_bootstrap(self::$config['bootstrap'], self::tests_dir());
        self::load_suites();
        return self::$config;
    }
    /**
     * @throws ConfigurationException
     */
    public static function load_bootstrap(string|false $bootstrap, string $path): void
    {
        if (!$bootstrap) {
            return;
        }
        $file = Path_Resolver::is_path_absolute($bootstrap) ? $bootstrap : rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $bootstrap;
        if (!file_exists($file)) {
            throw new Configuration_Exception("Bootstrap file {$file} can't be loaded");
        }
        require_once $file;
    }
    protected static function load_suites(): void
    {
        $suites = Finder::create()->files()->name('*.{suite,suite.dist}.yml')->in(self::$dir . DIRECTORY_SEPARATOR . self::$tests_dir)->depth('< 1')->sort_by_name();
        self::$suites = [];
        foreach (array_keys(self::$config['suites']) as $suite) {
            self::$suites[$suite] = $suite;
        }
        foreach ($suites as $suite) {
            preg_match('#(.*?)(\.suite|\.suite\.dist)\.yml#', $suite->get_filename(), $matches);
            self::$suites[$matches[1]] = $matches[1];
        }
    }
    private static function validate_paths(): void
    {
        if (empty(self::$config['paths']['tests'])) {
            throw new Configuration_Exception('Tests directory is not defined in Codeception config by key "paths: tests"');
        }
        if (empty(self::$config['paths']['data'])) {
            throw new Configuration_Exception('Data path is not defined in Codeception config by key "paths: data"');
        }
        if (empty(self::$config['paths']['support'])) {
            throw new Configuration_Exception('Helpers path is not defined in Codeception config by key "paths: support"');
        }
        self::$data_dir = self::$config['paths']['data'];
        self::$support_dir = self::$config['paths']['support'];
        self::$tests_dir = self::$config['paths']['tests'];
        self::$envs_dir = self::$config['paths']['envs'] ?? null;
        Autoload::add_namespace(self::$config['namespace'] . '\\' . self::$config['support_namespace'], self::support_dir());
    }
    /**
     * Returns suite configuration. Requires suite name and global config used (Configuration::config)
     *
     * @return array<string, string>
     * @throws ConfigurationException
     */
    public static function suite_settings(string $suite, array $config): array
    {
        if ($suite != $config['namespace'] && str_starts_with($suite, (string) $config['namespace'])) {
            $suite = ltrim(substr($suite, strlen((string) $config['namespace'])), '.');
        }
        if (!in_array($suite, self::$suites)) {
            throw new Configuration_Exception("Suite {$suite} was not loaded");
        }
        $global_conf = $config['settings'];
        foreach (['modules', 'coverage', 'support_namespace', 'namespace', 'groups', 'env', 'gherkin', 'extensions'] as $key) {
            if (isset($config[$key])) {
                $global_conf[$key] = $config[$key];
            }
        }
        $settings = self::merge_configs(self::$default_suite_settings, $global_conf);
        $settings = self::load_suite_config($suite, $config['paths']['tests'], $settings);
        if (isset($config['paths']['envs'])) {
            $env_conf = self::load_env_configs(self::$dir . DIRECTORY_SEPARATOR . $config['paths']['envs']);
            $settings = self::merge_configs($settings, $env_conf);
        }
        if (!$settings['path']) {
            $settings['path'] = $suite;
        }
        $config['paths']['tests'] = str_replace('/', DIRECTORY_SEPARATOR, (string) $config['paths']['tests']);
        $settings['path'] = self::$dir . DIRECTORY_SEPARATOR . $config['paths']['tests'] . DIRECTORY_SEPARATOR . $settings['path'] . DIRECTORY_SEPARATOR;
        $settings['suite'] = $suite;
        $settings['suite_namespace'] = $settings['namespace'] . '\\' . $suite;
        return $settings;
    }
    /**
     * Loads environments configuration from set directory
     *
     * @param string $path Path to the directory
     * @return array<string, mixed>
     */
    protected static function load_env_configs(string $path): array
    {
        if (isset(self::$env_config[$path])) {
            return self::$env_config[$path];
        }
        if (!is_dir($path)) {
            self::$env_config[$path] = [];
            return self::$env_config[$path];
        }
        $env_files = Finder::create()->files()->name('*.yml')->in($path)->depth('< 2');
        $env_config = [];
        foreach ($env_files as $env_file) {
            $env = str_replace(['.dist.yml', '.yml'], '', $env_file->get_filename());
            $env_config[$env] = [];
            $env_path = $path . ($env_file->get_relative_path() !== '' ? DIRECTORY_SEPARATOR . $env_file->get_relative_path() : '');
            foreach (['.dist.yml', '.yml'] as $suffix) {
                $env_conf = self::get_conf_from_file($env_path . DIRECTORY_SEPARATOR . $env . $suffix);
                $env_config[$env] = self::merge_configs($env_config[$env], $env_conf);
            }
        }
        self::$env_config[$path] = ['env' => $env_config];
        return self::$env_config[$path];
    }
    /**
     * Loads configuration from Yaml data
     *
     * @param string $contents Yaml config file contents
     * @param string $filename which is supposed to be loaded
     * @return array<string, mixed>
     * @throws ConfigurationException
     */
    protected static function get_conf_from_contents(string $contents, string $filename = '(.yml)'): array
    {
        if (self::$params) {
            $template = new Template($contents, "'%", "%'", 'json_encode');
            $template->set_vars(self::$params);
            $contents = $template->produce();
            $template = new Template($contents, '"%', '%"', 'json_encode');
            $template->set_vars(self::$params);
            $contents = $template->produce();
            $template = new Template($contents, '%', '%');
            $template->set_vars(self::$params);
            $contents = $template->produce();
        }
        try {
            $conf = Yaml::parse($contents);
        } catch (Parse_Exception $e) {
            throw new Configuration_Exception(sprintf("Error loading Yaml config from %s\n\n%s\nRead more about Yaml format https://goo.gl/9UPuEC", $filename, $e->get_message()));
        }
        if (!is_array($conf)) {
            throw new Configuration_Exception("Configuration file {$filename} is invalid or empty.");
        }
        return $conf;
    }
    /**
     * Loads configuration from Yaml file or returns given value if the file doesn't exist
     *
     * @param array<string, mixed> $nonExistentValue Value used if filename is not found
     * @return array<string, mixed>
     * @throws ConfigurationException
     */
    protected static function get_conf_from_file(string $filename, array $non_existent_value = []): array
    {
        if (!file_exists($filename)) {
            return $non_existent_value;
        }
        $contents = file_get_contents($filename);
        if ($contents === false) {
            throw new Configuration_Exception("Failed to read {$filename}");
        }
        return self::get_conf_from_contents($contents, $filename);
    }
    /**
     * @return string[]
     */
    public static function suites(): array
    {
        return self::$suites;
    }
    /**
     * Return list of enabled modules according suite config.
     *
     * @param array<string, mixed> $settings Suite settings
     * @return string[]
     */
    public static function modules(array $settings): array
    {
        return array_filter(array_map(fn($m): mixed => is_array($m) ? key($m) : $m, $settings['modules']['enabled'], array_keys($settings['modules']['enabled'])), fn($m): bool => !isset($settings['modules']['disabled']) || !in_array($m, $settings['modules']['disabled']));
    }
    public static function is_extension_enabled(string $extension_name): bool
    {
        return isset(self::$config['extensions']['enabled']) && in_array($extension_name, self::$config['extensions']['enabled']);
    }
    /**
     * Returns current path to `_data` dir.
     * Use it to store database fixtures, sql dumps, or other files required by your tests.
     */
    public static function data_dir(): string
    {
        return self::$dir . DIRECTORY_SEPARATOR . self::$data_dir . DIRECTORY_SEPARATOR;
    }
    /**
     * Return current path to `_helpers` dir.
     * Helpers are custom modules.
     */
    public static function support_dir(): string
    {
        return self::$dir . DIRECTORY_SEPARATOR . self::$support_dir . DIRECTORY_SEPARATOR;
    }
    /**
     * Returns actual path to current `_output` dir.
     * Use it in Helpers or Groups to save result or temporary files.
     *
     * @throws ConfigurationException
     */
    public static function output_dir(): string
    {
        if (self::$output_dir === '') {
            throw new Configuration_Exception('Path for output not specified. Please, set output path in global config');
        }
        $dir = self::$output_dir . DIRECTORY_SEPARATOR;
        if (!codecept_is_path_absolute($dir)) {
            $dir = self::$dir . DIRECTORY_SEPARATOR . $dir;
        }
        if (!file_exists($dir)) {
            @mkdir($dir, 0777, true);
        }
        if (!is_writable($dir)) {
            @chmod($dir, 0777);
        }
        if (!is_writable($dir)) {
            throw new Configuration_Exception("Path for output is not writable. Please, set appropriate access mode for output path: {$dir}");
        }
        return $dir;
    }
    /**
     * Returns path to the root of your project.
     * Basically returns path to current `codeception.yml` loaded.
     * Use this method instead of `__DIR__`, `getcwd()` or anything else.
     */
    public static function project_dir(): string
    {
        return self::$dir . DIRECTORY_SEPARATOR;
    }
    /**
     * Returns path to the base dir for config which consists with included setup
     * Returns path to `codeception.yml` which was executed.
     * If config doesn't have "include" section the result is the same as `projectDir()`
     */
    public static function base_dir(): string
    {
        return self::$base_dir . DIRECTORY_SEPARATOR;
    }
    /**
     * Returns path to tests directory
     */
    public static function tests_dir(): string
    {
        return self::$dir . DIRECTORY_SEPARATOR . self::$tests_dir . DIRECTORY_SEPARATOR;
    }
    /**
     * Return current path to `_envs` dir.
     * Use it to store environment specific configuration.
     */
    public static function envs_dir(): string
    {
        return self::$envs_dir ? self::$dir . DIRECTORY_SEPARATOR . self::$envs_dir . DIRECTORY_SEPARATOR : '';
    }
    /**
     * Is this a meta-configuration file that just points to other `codeception.yml`?
     * If so, it may have no tests by itself.
     */
    public static function is_empty(): bool
    {
        return !self::$tests_dir;
    }
    /**
     * Adds parameters to config
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public static function append(array $config = []): array
    {
        self::$config = self::merge_configs(self::$config ?? [], $config);
        if (isset(self::$config['paths']['output'])) {
            self::$output_dir = self::$config['paths']['output'];
        }
        if (isset(self::$config['paths']['data'])) {
            self::$data_dir = self::$config['paths']['data'];
        }
        if (isset(self::$config['paths']['support'])) {
            self::$support_dir = self::$config['paths']['support'];
        }
        if (isset(self::$config['paths']['tests'])) {
            self::$tests_dir = self::$config['paths']['tests'];
        }
        return self::$config;
    }
    public static function merge_configs(array $a1, array $a2): array
    {
        if (isset($a1[0], $a2[0])) {
            return array_values(array_unique(array_merge_recursive($a2, $a1), SORT_REGULAR));
        }
        $res = [];
        foreach ($a2 as $k2 => $v2) {
            if (!isset($a1[$k2]) || !is_array($a1[$k2])) {
                $res[$k2] = $v2;
                unset($a1[$k2]);
                continue;
            }
            if (is_array($v2)) {
                $res[$k2] = self::merge_configs($a1[$k2], $v2);
                unset($a1[$k2]);
            }
        }
        foreach ($a1 as $k1 => $v1) {
            $res[$k1] = $v1;
        }
        return $res;
    }
    /**
     * Loads config from *.dist.suite.yml and *.suite.yml
     *
     * @param array<string ,mixed> $settings
     * @return array<string ,mixed>
     * @throws ConfigurationException
     */
    protected static function load_suite_config(string $suite, string $path, array $settings): array
    {
        if (isset(self::$config['suites'][$suite])) {
            return self::merge_configs($settings, self::$config['suites'][$suite]);
        }
        $suite_dir = self::$dir . DIRECTORY_SEPARATOR . $path;
        $suite_dist = self::get_conf_from_file($suite_dir . DIRECTORY_SEPARATOR . "{$suite}.suite.dist.yml");
        $suite_conf = self::get_conf_from_file($suite_dir . DIRECTORY_SEPARATOR . "{$suite}.suite.yml");
        if (isset($suite_conf['extends'])) {
            $preset = Path_Resolver::is_path_absolute($suite_conf['extends']) ? $suite_conf['extends'] : realpath($suite_dir . DIRECTORY_SEPARATOR . $suite_conf['extends']);
            if ($preset === false) {
                throw new Configuration_Exception(sprintf('Configuration file %s does not exist', $suite_conf['extends']));
            }
            if (file_exists($preset)) {
                $settings = self::merge_configs(self::get_conf_from_file($preset), $settings);
            }
        }
        $settings = self::merge_configs($settings, $suite_dist);
        return self::merge_configs($settings, $suite_conf);
    }
    /**
     * Replaces wildcarded items in include array with real paths.
     *
     * @param string[] $includes
     * @return string[]
     * @throws ConfigurationException
     */
    protected static function expand_wildcarded_includes(array $includes): array
    {
        if ($includes === []) {
            return $includes;
        }
        $expanded = [];
        foreach ($includes as $include) {
            $expanded = array_merge($expanded, self::expand_wildcards_for($include));
        }
        return $expanded;
    }
    /**
     * Finds config files in given wildcarded include path.
     * Returns the expanded paths or the original if not a wildcard.
     *
     * @return string[]
     * @throws ConfigurationException
     */
    protected static function expand_wildcards_for(string $include): array
    {
        if (!preg_match('#[?.*]#', $include)) {
            return [$include];
        }
        try {
            $finder = Finder::create()->files()->name('/codeception(\.dist\.yml|\.yml)/')->in(self::$dir . DIRECTORY_SEPARATOR . $include);
        } catch (InvalidArgumentException) {
            throw new Configuration_Exception("Configuration file(s) could not be found in \"{$include}\".");
        }
        $paths = [];
        foreach ($finder as $file) {
            $paths[] = codecept_relative_path($file->get_path());
        }
        return array_unique($paths);
    }
    /**
     * @param array<string, mixed> $settings
     * @throws ConfigurationException
     */
    private static function prepare_params(array $settings): void
    {
        self::$params = [];
        foreach ($settings['params'] as $param_storage) {
            self::$params = array_merge(self::$params, Params_Loader::load($param_storage));
        }
    }
}
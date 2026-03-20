<?php

declare (strict_types=1);
namespace Codeception\Lib;

use function codecept_absolute_path;
use function codecept_relative_path;
use Codeception\Exception\Configuration_Exception;
use Dotenv\Dotenv as PhpDotenv;
use Dotenv\Repository\Repository_Builder;
use Exception;
use function file_exists;
use function file_get_contents;
use function parse_ini_file;
use function preg_match;
use function simplexml_load_file;
use Simple_Xml_Element;
use Symfony\Component\Dotenv\Dotenv as SymfonyDotenv;
use Symfony\Component\Yaml\Yaml;
class Params_Loader
{
    /**
     * @throws ConfigurationException
     */
    public static function load(array|string $param_storage): array
    {
        if (is_array($param_storage)) {
            return $param_storage;
        }
        if (in_array($param_storage, ['env', 'environment'])) {
            return $_SERVER;
        }
        $params_file = codecept_absolute_path($param_storage);
        if (!file_exists($params_file)) {
            throw new Configuration_Exception("Params file {$params_file} not found");
        }
        $loader_mappings = ['loadYamlFile' => '#\.ya?ml$#', 'loadIniFile' => '#\.ini$#', 'loadPhpFile' => '#\.php$#', 'loadDotEnvFile' => '#(\.env(\.|$))#', 'loadXmlFile' => '#\.xml$#'];
        foreach ($loader_mappings as $method => $pattern) {
            if (preg_match($pattern, $param_storage)) {
                try {
                    return self::$method($params_file);
                } catch (Exception $e) {
                    throw new Configuration_Exception("Failed loading params from {$params_file}\n" . $e->get_message());
                }
            }
        }
        throw new Configuration_Exception("Params can't be loaded from `{$params_file}`.");
    }
    /**
     * @throws ConfigurationException
     */
    private static function load_ini_file(string $file): array
    {
        $params = parse_ini_file($file);
        return self::validate_params($params, $file);
    }
    /**
     * @throws ConfigurationException
     */
    private static function load_php_file(string $file): array
    {
        $params = require $file;
        return self::validate_params($params, $file);
    }
    /**
     * @throws ConfigurationException
     */
    private static function load_yaml_file(string $file): array
    {
        $params = Yaml::parse(self::get_file_contents($file));
        return self::validate_params($params['parameters'] ?? $params, $file);
    }
    /**
     * @throws ConfigurationException
     */
    private static function load_xml_file(string $file): array
    {
        if (!extension_loaded('simplexml')) {
            throw new Configuration_Exception('`simplexml` extension is required to parse .xml files.');
        }
        $params_to_array = function (Simple_Xml_Element $params) use (&$params_to_array): array {
            $a = [];
            foreach ($params as $param) {
                $key = isset($param['key']) ? (string) $param['key'] : $param->get_name();
                $type = isset($param['type']) ? (string) $param['type'] : 'string';
                $value = (string) $param;
                $a[$key] = match ($type) {
                    'bool', 'boolean', 'int', 'integer', 'float', 'double' => settype($value, $type),
                    'constant' => constant($value),
                    'collection' => $params_to_array($param),
                    default => (string) $param,
                };
            }
            return $a;
        };
        $simple_xml_element = simplexml_load_file($file);
        if ($simple_xml_element === false) {
            throw new Configuration_Exception("Params can't be loaded from `{$file}`.");
        }
        $params = $params_to_array($simple_xml_element);
        return self::validate_params($params, $file);
    }
    /**
     * @throws ConfigurationException
     */
    private static function load_dot_env_file(string $file): array
    {
        if (class_exists(Php_Dotenv::class) && class_exists(Repository_Builder::class) && method_exists(Repository_Builder::class, 'createWithDefaultAdapters')) {
            $repository = Repository_Builder::create_with_default_adapters()->make();
            $dotenv = Php_Dotenv::create($repository, codecept_root_dir(), codecept_relative_path($file));
            return $dotenv->load();
        }
        if (class_exists(Symfony_Dotenv::class)) {
            $symfony_dot_env = new Symfony_Dotenv();
            $values = $symfony_dot_env->parse(self::get_file_contents($file), $file);
            $symfony_dot_env->populate($values);
            return $values;
        }
        throw new Configuration_Exception("`vlucas/phpdotenv:5.*` or `symfony/dotenv` library is required to parse .env files.\n" . 'Please install it via composer, e.g.: composer require vlucas/phpdotenv');
    }
    /**
     * @throws ConfigurationException
     */
    private static function get_file_contents(string $file): string
    {
        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new Configuration_Exception("Params can't be loaded from `{$file}`.");
        }
        return $contents;
    }
    /**
     * @throws ConfigurationException
     */
    private static function validate_params(mixed $params, string $file): array
    {
        if (!is_array($params)) {
            throw new Configuration_Exception("Params can't be loaded from `{$file}`.");
        }
        return $params;
    }
}
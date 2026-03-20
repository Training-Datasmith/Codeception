<?php

declare (strict_types=1);
namespace Codeception\Command\Shared;

use function array_merge_recursive;
use function array_pop;
use function array_shift;
use function class_exists;
use Codeception\Configuration;
use function count;
use function explode;
use InvalidArgumentException;
use function str_repeat;
use Symfony\Component\Console\Exception\Invalid_Option_Exception;
use Symfony\Component\Yaml\Exception\Parse_Exception;
use Symfony\Component\Yaml\Yaml;
use function ucfirst;
trait Config_Trait
{
    protected function get_suite_config(string $suite): array
    {
        return Configuration::suite_settings($suite, $this->get_global_config());
    }
    protected function get_global_config(?string $conf = null): array
    {
        return Configuration::config($conf);
    }
    /**
     * @return string[]
     */
    protected function get_suites(): array
    {
        return Configuration::suites();
    }
    /** @param string[] $configOptions */
    protected function override_config(array $config_options): array
    {
        $updated_config = [];
        foreach ($config_options as $option) {
            $keys = explode(': ', $option);
            if (count($keys) < 2) {
                throw new InvalidArgumentException('--override should have config passed as "key: value"');
            }
            $value = array_pop($keys);
            $yaml = '';
            for ($ind = 0; count($keys); $ind += 2) {
                $yaml .= "\n" . str_repeat(' ', $ind) . array_shift($keys) . ': ';
            }
            $yaml .= $value;
            try {
                $config = Yaml::parse($yaml);
            } catch (Parse_Exception $e) {
                throw new \Codeception\Exception\Parse_Exception("Overridden config can't be parsed: \n{$yaml}\n" . $e->get_parsed_line());
            }
            $updated_config = array_merge_recursive($updated_config, $config);
        }
        return Configuration::append($updated_config);
    }
    /** @param string[] $extensions */
    protected function enable_extensions(array $extensions): array
    {
        $config = ['extensions' => ['enabled' => []]];
        foreach ($extensions as $name) {
            if (!class_exists($name)) {
                $class_name = 'Codeception\Extension\\' . ucfirst($name);
                if (!class_exists($class_name)) {
                    throw new Invalid_Option_Exception("Extension {$name} can't be loaded (tried by {$name} and {$class_name})");
                }
                $config['extensions']['enabled'][] = $class_name;
                continue;
            }
            $config['extensions']['enabled'][] = $name;
        }
        return Configuration::append($config);
    }
}
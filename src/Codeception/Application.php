<?php

declare (strict_types=1);
namespace Codeception;

use Codeception\Exception\Configuration_Exception;
use Exception;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\Argv_Input as SymfonyArgvInput;
use Symfony\Component\Console\Input\Input_Definition;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Console_Output;
use Symfony\Component\Console\Output\Output_Interface;
class Application extends Base_Application
{
    protected ?Symfony_Argv_Input $core_arguments = null;
    /**
     * Register commands from config file
     *
     *  extensions:
     *      commands:
     *          - Project\Command\MyCustomCommand
     */
    public function register_custom_commands(): void
    {
        $output = new Console_Output();
        try {
            $this->read_custom_commands_from_config();
        } catch (Configuration_Exception $e) {
            if ($e->get_code() === 404) {
                return;
            }
            parent::render_throwable($e, $output);
            exit(1);
        } catch (Exception $e) {
            parent::render_throwable($e, $output);
            exit(1);
        }
    }
    /**
     * Search custom commands and register them.
     *
     * @throws ConfigurationException
     */
    protected function read_custom_commands_from_config(): void
    {
        $this->get_core_arguments();
        // Maybe load outside config file
        $config = Configuration::config();
        if (empty($config['extensions']['commands'])) {
            return;
        }
        foreach ($config['extensions']['commands'] as $command_class) {
            $command = new $command_class($this->get_custom_command_name($command_class));
            // addCommand() is available since symfony 7.4
            if (method_exists($this, 'addCommand')) {
                $this->add_command($command);
            } else {
                $this->add($command);
            }
        }
    }
    /**
     * Validate and get the name of the command
     *
     * @param class-string $commandClass A class that implement the `\Codeception\CustomCommandInterface`.
     * @throws ConfigurationException
     */
    protected function get_custom_command_name(string $command_class): string
    {
        if (!class_exists($command_class)) {
            throw new Configuration_Exception("Extension: Command class {$command_class} not found");
        }
        if (!is_subclass_of($command_class, Custom_Command_Interface::class)) {
            throw new Configuration_Exception("Extension: Command {$command_class} must implement the interface `Codeception\\CustomCommandInterface`");
        }
        return $command_class::get_command_name();
    }
    /**
     * To cache Class ArgvInput
     *
     * @inheritDoc
     */
    public function run(?Input_Interface $input = null, ?Output_Interface $output = null): int
    {
        if (!$input instanceof Input_Interface) {
            $input = $this->get_core_arguments();
        }
        if ((PHP_VERSION_ID < 80500 || 'cli' !== php_sapi_name()) && !ini_get('register_argc_argv')) {
            throw new Configuration_Exception('register_argc_argv must be set to On for running Codeception');
        }
        return parent::run($input, $output);
    }
    /**
     * Add global a --config option.
     */
    protected function get_default_input_definition(): Input_Definition
    {
        $input_definition = parent::get_default_input_definition();
        $input_definition->add_option(new Input_Option('config', 'c', Input_Option::VALUE_OPTIONAL, 'Use custom path for config'));
        return $input_definition;
    }
    /**
     * Search for --config Option and if found will be loaded
     *
     * example:
     * -c file.yml|dir
     * -cfile.yml|dir
     * --config file.yml|dir
     * --config=file.yml|dir
     */
    protected function get_core_arguments(): Symfony_Argv_Input
    {
        if ($this->core_arguments instanceof Symfony_Argv_Input) {
            return $this->core_arguments;
        }
        $argv_without_config = [];
        $argv = $_SERVER['argv'] ?? [];
        for ($i = 0, $count = count($argv); $i < $count; ++$i) {
            if (preg_match('#^(?:-([^c-]*)?c|--config(?:=|$))(.*)$#', (string) $argv[$i], $match)) {
                $value = $match[2] !== '' ? $match[2] : $argv[$i + 1] ?? '';
                if ($value !== '') {
                    $this->preload_configuration($value);
                    if ($match[2] === '') {
                        ++$i;
                    }
                }
                if (!empty($match[1])) {
                    $argv_without_config[] = '-' . $match[1];
                }
                continue;
            }
            $argv_without_config[] = $argv[$i];
        }
        return $this->core_arguments = new Symfony_Argv_Input($argv_without_config);
    }
    /**
     * Preload Configuration, the config option is use.
     *
     * @param string $configFile Path to Configuration
     * @throws ConfigurationException
     */
    protected function preload_configuration(string $config_file): void
    {
        try {
            Configuration::config($config_file);
        } catch (Configuration_Exception $e) {
            if ($e->get_code() === 404) {
                throw new Configuration_Exception("Your configuration file `{$config_file}` could not be found.", 405);
            }
            throw $e;
        }
    }
}
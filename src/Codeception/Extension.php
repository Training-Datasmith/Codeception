<?php

declare (strict_types=1);
namespace Codeception;

use function array_keys;
use function array_merge;
use Codeception\Event\Suite_Event;
use Codeception\Exception\Module_Require_Exception;
use Codeception\Extension\Suite_Init_Subscriber_Trait;
use Codeception\Lib\Console\Output;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
/**
 * A base class for all Codeception Extensions and GroupObjects
 *
 * Available Properties:
 *
 * * config: current extension configuration
 * * options: passed running options
 */
abstract class Extension implements Event_Subscriber_Interface
{
    use Suite_Init_Subscriber_Trait;
    /**
     * @var array<int|string, mixed>
     */
    protected array $config = [];
    protected Output $output;
    protected array $global_config = [];
    /**
     * @var array<string, Module>
     */
    private array $modules = [];
    public function __construct(array $config, protected array $options)
    {
        $this->config = array_merge($this->config, $config);
        $this->output = new Output($options);
        $this->_initialize();
    }
    public function receive_module_container(Suite_Event $event): void
    {
        $this->modules = $event->get_suite()->get_modules();
    }
    /**
     * Pass config variables that should be injected into global config.
     */
    public function _reconfigure(array $config = []): void
    {
        Configuration::append($config);
    }
    /**
     * You can do all preparations here. No need to override constructor.
     * Also, you can skip calling `_reconfigure` if you don't need to.
     */
    public function _initialize(): void
    {
        $this->_reconfigure();
        // hook for BC only.
    }
    /**
     * @param string|iterable $messages The message as an iterable of strings or a single string
     */
    protected function write(iterable|string $messages): void
    {
        if (empty($this->options['silent']) && $messages) {
            $this->output->write($messages);
        }
    }
    /**
     * @param string|iterable $messages The message as an iterable of strings or a single string
     */
    protected function writeln(iterable|string $messages): void
    {
        if (empty($this->options['silent']) && $messages) {
            $this->output->writeln($messages);
        }
    }
    public function has_module(string $name): bool
    {
        return isset($this->modules[$name]);
    }
    /**
     * @return string[]
     */
    public function get_current_module_names(): array
    {
        return array_keys($this->modules);
    }
    public function get_module(string $name): Module
    {
        if (!$this->has_module($name)) {
            throw new Module_Require_Exception($name, 'module is not enabled');
        }
        return $this->modules[$name];
    }
    public function get_tests_dir(): string
    {
        return Configuration::tests_dir();
    }
    public function get_log_dir(): string
    {
        return Configuration::output_dir();
    }
    public function get_data_dir(): string
    {
        return Configuration::data_dir();
    }
    public function get_root_dir(): string
    {
        return Configuration::project_dir();
    }
    public function get_global_config(): array
    {
        return Configuration::config();
    }
}
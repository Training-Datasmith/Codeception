<?php

declare (strict_types=1);
namespace Codeception\Subscriber;

use function class_exists;
use Codeception\Configuration;
use Codeception\Event\Suite_Event;
use Codeception\Events;
use Codeception\Exception\Configuration_Exception;
use function is_array;
use function key;
use function reset;
use Symfony\Component\Event_Dispatcher\Event_Dispatcher;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
class Extension_Loader implements Event_Subscriber_Interface
{
    use Shared\Static_Events_Trait;
    /**
     * @var array<string, string>
     */
    protected static array $events = [Events::MODULE_INIT => 'registerSuiteExtensions', Events::SUITE_AFTER => 'stopSuiteExtensions'];
    /**
     * @var array<string, mixed>
     */
    protected array $config = [];
    /**
     * @var array<string, mixed>
     */
    protected array $options = [];
    /**
     * @var array<class-string, EventSubscriberInterface>
     */
    protected array $global_extensions = [];
    /**
     * @var array<class-string, EventSubscriberInterface>
     */
    protected array $suite_extensions = [];
    public function __construct(protected Event_Dispatcher $dispatcher)
    {
        $this->config = Configuration::config();
    }
    /**
     * @param array<string, mixed> $options
     * @throws ConfigurationException
     */
    public function boot_global_extensions(array $options): void
    {
        $this->options = $options;
        $this->global_extensions = $this->boot_extensions($this->config);
    }
    public function register_global_extensions(): void
    {
        foreach ($this->global_extensions as $extension) {
            $this->dispatcher->add_subscriber($extension);
        }
    }
    public function register_suite_extensions(Suite_Event $event): void
    {
        $suite_config = $event->get_settings();
        $extensions = $this->boot_extensions($suite_config);
        $this->suite_extensions = [];
        foreach ($extensions as $extension) {
            $extension_class = $extension::class;
            if (isset($this->global_extensions[$extension_class])) {
                continue;
                // already globally enabled
            }
            $this->dispatcher->add_subscriber($extension);
            $this->suite_extensions[$extension_class] = $extension;
        }
    }
    public function stop_suite_extensions(): void
    {
        foreach ($this->suite_extensions as $extension) {
            $this->dispatcher->remove_subscriber($extension);
        }
        $this->suite_extensions = [];
    }
    /**
     * @param array<string, mixed> $config
     * @return array<class-string, EventSubscriberInterface>
     * @throws ConfigurationException
     */
    protected function boot_extensions(array $config): array
    {
        $extensions = [];
        foreach ($config['extensions']['enabled'] as $extension_class) {
            if (is_array($extension_class)) {
                $extension_class = key($extension_class);
            }
            if (!class_exists($extension_class)) {
                throw new Configuration_Exception("Class `{$extension_class}` is not defined. Autoload it or include into " . "'_bootstrap.php' file of 'tests' directory");
            }
            $extension_config = $this->get_extension_config($extension_class, $config);
            $extension = new $extension_class($extension_config, $this->options);
            if (!$extension instanceof Event_Subscriber_Interface) {
                throw new Configuration_Exception("Class {$extension_class} is not an EventListener. Please create it as Extension or GroupObject.");
            }
            $extensions[$extension::class] = $extension;
        }
        return $extensions;
    }
    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function get_extension_config(string $extension, array $config): array
    {
        $extension_config = $config['extensions']['config'][$extension] ?? [];
        if (!isset($config['extensions']['enabled'])) {
            return $extension_config;
        }
        if (!is_array($config['extensions']['enabled'])) {
            return $extension_config;
        }
        foreach ($config['extensions']['enabled'] as $enabled_extensions_config) {
            if (!is_array($enabled_extensions_config)) {
                continue;
            }
            $enabled_extension = key($enabled_extensions_config);
            if ($enabled_extension === $extension) {
                $enabled_extension_config = reset($enabled_extensions_config);
                if (!is_array($enabled_extension_config)) {
                    return $extension_config;
                }
                return Configuration::merge_configs($enabled_extension_config, $extension_config);
            }
        }
        return $extension_config;
    }
}
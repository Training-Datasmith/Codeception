<?php

declare (strict_types=1);
namespace Codeception;

use Codeception\Coverage\Subscriber\Local;
use Codeception\Coverage\Subscriber\Local_Server;
use Codeception\Coverage\Subscriber\Printer as CoveragePrinter;
use Codeception\Coverage\Subscriber\Remote_Server;
use Codeception\Event\Print_Result_Event;
use Codeception\Exception\Configuration_Exception;
use Codeception\Lib\Console\Output;
use Codeception\Lib\Interfaces\Console_Printer;
use Codeception\Lib\Notification;
use Codeception\Reporter\Html_Reporter;
use Codeception\Reporter\J_Unit_Reporter;
use Codeception\Reporter\Php_Unit_Reporter;
use Codeception\Reporter\Report_Printer;
use Codeception\Subscriber\Auto_Rebuild;
use Codeception\Subscriber\Before_After_Test;
use Codeception\Subscriber\Bootstrap;
use Codeception\Subscriber\Console;
use Codeception\Subscriber\Dependencies;
use Codeception\Subscriber\Deprecation;
use Codeception\Subscriber\Error_Handler;
use Codeception\Subscriber\Extension_Loader;
use Codeception\Subscriber\Fail_Fast;
use Codeception\Subscriber\Graceful_Termination;
use Codeception\Subscriber\Module;
use Codeception\Subscriber\Prepare_Test;
use Symfony\Component\Event_Dispatcher\Event_Dispatcher;
class Codecept
{
    /**
     * @var string
     */
    public const VERSION = '5.3.5';
    protected Result_Aggregator $result_aggregator;
    protected Event_Dispatcher $dispatcher;
    protected Extension_Loader $extension_loader;
    protected array $options = ['silent' => false, 'debug' => false, 'steps' => false, 'html' => false, 'xml' => false, 'phpunit-xml' => false, 'no-redirect' => true, 'report' => false, 'colors' => false, 'coverage' => false, 'coverage-xml' => false, 'coverage-html' => false, 'coverage-text' => false, 'coverage-crap4j' => false, 'coverage-cobertura' => false, 'coverage-phpunit' => false, 'disable-coverage-php' => false, 'groups' => null, 'excludeGroups' => null, 'filter' => null, 'shard' => null, 'env' => null, 'fail-fast' => 0, 'ansi' => true, 'verbosity' => 1, 'interactive' => true, 'no-rebuild' => false, 'quiet' => false];
    protected array $config = [];
    protected array $extensions = [];
    private readonly Output $output;
    public function __construct(array $options = [])
    {
        $this->result_aggregator = new Result_Aggregator();
        $this->dispatcher = new Event_Dispatcher();
        $this->extension_loader = new Extension_Loader($this->dispatcher);
        $this->extension_loader->boot_global_extensions($this->merge_options($options));
        $this->config = Configuration::config();
        $this->options = $this->merge_options($options);
        $this->output = new Output($this->options);
        $this->register_subscribers();
    }
    /**
     * Merges given options with default values and current configuration
     *
     * @throws ConfigurationException
     */
    protected function merge_options(array $options): array
    {
        return array_merge($this->options, Configuration::config()['settings'], $options);
    }
    /**
     * \Symfony\Component\EventDispatcher\EventSubscriberInterface[] $subscribers
     */
    private function add_subscribers(array $subscribers): void
    {
        foreach ($subscribers as $subscriber) {
            $this->dispatcher->add_subscriber($subscriber);
        }
    }
    public function register_subscribers(): void
    {
        $this->add_subscribers([new Graceful_Termination($this->result_aggregator), new Error_Handler(), new Dependencies(), new Bootstrap(), new Prepare_Test(), new Module(), new Before_After_Test()]);
        if (!$this->options['no-rebuild']) {
            $this->dispatcher->add_subscriber(new Auto_Rebuild());
        }
        if ($this->options['fail-fast'] > 0) {
            $this->dispatcher->add_subscriber(new Fail_Fast($this->options['fail-fast'], $this->result_aggregator));
        }
        if ($this->options['coverage']) {
            $this->add_subscribers([new Local($this->options), new Local_Server($this->options), new Remote_Server($this->options), new Coverage_Printer($this->options, $this->output)]);
        }
        if ($this->options['report']) {
            $this->dispatcher->add_subscriber(new Report_Printer($this->options));
        }
        $this->dispatcher->add_subscriber($this->extension_loader);
        $this->extension_loader->register_global_extensions();
        if (!$this->options['silent'] && !$this->is_console_printer_subscribed()) {
            $this->dispatcher->add_subscriber(new Console($this->options));
        }
        $this->dispatcher->add_subscriber(new Deprecation($this->options));
        $this->register_reporters();
    }
    private function is_console_printer_subscribed(): bool
    {
        foreach ($this->dispatcher->get_listeners() as $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof Console_Printer || is_array($listener) && $listener[0] instanceof Console_Printer) {
                    return true;
                }
            }
        }
        return false;
    }
    private function register_reporters(): void
    {
        if (isset($this->config['reporters'])) {
            Notification::warning("'reporters' option is not supported! Custom reporters must be reimplemented as extensions.", '');
        }
        $map = ['html' => fn(): \Codeception\Reporter\Html_Reporter => new Html_Reporter($this->options, $this->output), 'xml' => fn(): \Codeception\Reporter\J_Unit_Reporter => new J_Unit_Reporter($this->options, $this->output), 'phpunit-xml' => fn(): \Codeception\Reporter\Php_Unit_Reporter => new Php_Unit_Reporter($this->options, $this->output)];
        foreach ($map as $flag => $create) {
            if ($this->options[$flag]) {
                $this->dispatcher->add_subscriber($create());
            }
        }
    }
    public function run(string $suite, ?string $test = null, ?array $config = null): void
    {
        ini_set('memory_limit', $this->config['settings']['memory_limit'] ?? '1024M');
        $config = Configuration::suite_settings($suite, $config ?: Configuration::config());
        $selected_environments = $this->options['env'];
        if (!$selected_environments || empty($config['env'])) {
            $this->run_suite($config, $suite, $test);
            return;
        }
        // Iterate over all unique environment sets and runs the given suite with each of the merged configurations.
        foreach (array_unique($selected_environments) as $env_list) {
            $env_set = explode(',', (string) $env_list);
            $suite_env_config = $config;
            $env_configs = [];
            foreach ($env_set as $current_env) {
                // The $settings['env'] actually contains all parsed configuration files as a
                // filename => filecontents key-value array. If there is no configuration file for the
                // $currentEnv the merge will be skipped.
                if (!array_key_exists($current_env, $config['env'])) {
                    return;
                }
                if (is_array($config['env'][$current_env])) {
                    $suite_env_config = Configuration::merge_configs($suite_env_config, $config['env'][$current_env]);
                }
                $env_configs[] = $current_env;
            }
            $suite_env_config['current_environment'] = implode(',', $env_configs);
            $suite_to_run = $suite . (empty($env_list) ? '' : ' (' . implode(', ', $env_set) . ')');
            $this->run_suite($suite_env_config, $suite_to_run, $test);
        }
    }
    public function run_suite(array $settings, string $suite, ?string $test = null): void
    {
        $settings['shard'] = $this->options['shard'];
        $suite_manager = new Suite_Manager($this->dispatcher, $suite, $settings, $this->options);
        $suite_manager->initialize();
        mt_srand($this->options['seed']);
        $suite_manager->load_tests($test);
        mt_srand();
        $suite_manager->run($this->result_aggregator);
    }
    public static function version_string(): string
    {
        return 'Codeception PHP Testing Framework v' . self::VERSION;
    }
    public function print_result(): void
    {
        $this->dispatcher->dispatch(new Print_Result_Event($this->result_aggregator), Events::RESULT_PRINT_AFTER);
    }
    public function get_result_aggregator(): Result_Aggregator
    {
        return $this->result_aggregator;
    }
    public function get_options(): array
    {
        return $this->options;
    }
    public function get_dispatcher(): Event_Dispatcher
    {
        return $this->dispatcher;
    }
}
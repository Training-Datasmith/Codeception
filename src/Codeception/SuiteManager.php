<?php

declare (strict_types=1);
namespace Codeception;

use Codeception\Command\Shared\Actor_Trait;
use Codeception\Lib\Di;
use Codeception\Lib\Group_Manager;
use Codeception\Lib\Module_Container;
use Codeception\Lib\Notification;
use Codeception\Test\Descriptor;
use Codeception\Test\Filter;
use Codeception\Test\Interfaces\Scenario_Driven;
use Codeception\Test\Loader;
use Codeception\Test\Test;
use Codeception\Test\Test_Case_Wrapper;
use Codeception\Test\Unit;
use Php_Unit\Runner\Version as PHPUnitVersion;
use Symfony\Component\Event_Dispatcher\Event_Dispatcher;
class Suite_Manager
{
    use Actor_Trait;
    protected ?Suite $suite = null;
    protected Group_Manager $group_manager;
    protected Module_Container $module_container;
    protected Di $di;
    protected string $env = '';
    protected array $settings = [];
    private Filter $test_filter;
    public function __construct(protected ?Event_Dispatcher $dispatcher, string $name, array $settings, array $options)
    {
        $this->settings = $settings;
        $this->di = new Di();
        $this->group_manager = new Group_Manager($settings['groups']);
        $this->module_container = new Module_Container($this->di, $settings);
        foreach (Configuration::modules($this->settings) as $module_name) {
            $this->module_container->create($module_name);
        }
        $this->module_container->validate_conflicts();
        $this->env = $settings['current_environment'] ?? '';
        $this->test_filter = new Filter($options['groups'] ?? null, $options['excludeGroups'] ?? null, $options['filter'] ?? null);
        $this->suite = $this->create_suite($name);
    }
    public function initialize(): void
    {
        $this->dispatch(Events::MODULE_INIT);
        foreach ($this->module_container->all() as $module) {
            $module->_initialize();
        }
        if ($this->settings['actor'] && !file_exists(Configuration::support_dir() . $this->settings['actor'] . '.php')) {
            throw new Exception\Configuration_Exception($this->settings['actor'] . " class doesn't exist in suite folder.\nRun the 'build' command to generate it");
        }
        $this->dispatch(Events::SUITE_INIT);
        ini_set('xdebug.show_exception_trace', '0');
        // See https://github.com/symfony/symfony/issues/7646
    }
    public function load_tests(?string $path = null): void
    {
        $loader = new Loader($this->settings);
        $loader->load_tests($path);
        $tests = $loader->get_tests();
        if ($this->settings['shuffle']) {
            shuffle($tests);
        }
        foreach ($tests as $test) {
            $this->add_to_suite($test);
        }
        $this->suite->reorder_dependencies();
    }
    protected function add_to_suite(Test $test): void
    {
        if (!$this->test_filter->is_name_accepted($test)) {
            return;
        }
        $this->configure_test($test);
        $this->check_environment_exists($test);
        if (!$this->is_executed_in_current_environment($test)) {
            return;
        }
        $groups = $this->group_manager->groups_for_test($test);
        if (!$this->test_filter->is_group_accepted($test, $groups)) {
            return;
        }
        $this->suite->add_test($test);
        if ($groups !== []) {
            $test->get_metadata()->set_groups($groups);
        }
    }
    protected function create_suite(string $name): Suite
    {
        if ($this->settings['namespace']) {
            $name = $this->settings['namespace'] . '.' . $name;
        }
        $suite = new Suite($this->dispatcher, $name);
        $suite->set_base_name(preg_replace('#\s.+$#', '', $name));
        $suite->set_modules($this->module_container->all());
        $suite->report_useless_tests(!empty($this->settings['report_useless_tests']));
        $suite->backup_globals(!empty($this->settings['backup_globals']));
        $suite->be_strict_about_changes_to_global_state(!empty($this->settings['be_strict_about_changes_to_global_state']));
        $suite->disallow_test_output(!empty($this->settings['disallow_test_output']));
        if (Php_Unit_Version::series() >= 10) {
            $suite->init_php_unit_configuration();
        }
        return $suite;
    }
    public function run(Result_Aggregator $result_aggregator): void
    {
        $this->dispatch(Events::SUITE_BEFORE);
        try {
            unset($GLOBALS['app']);
            $this->suite->run($result_aggregator);
        } finally {
            $this->dispatch(Events::SUITE_AFTER);
        }
    }
    public function get_suite(): Suite
    {
        return $this->suite;
    }
    public function get_module_container(): Module_Container
    {
        return $this->module_container;
    }
    protected function check_environment_exists(Test_Interface $test): void
    {
        $envs = $test->get_metadata()->get_env();
        if ($envs === [] || !isset($this->settings['env'])) {
            return;
        }
        $missing = array_diff($envs, array_keys($this->settings['env']));
        foreach ($missing as $env) {
            Notification::warning("Environment {$env} was not configured but used in test", Descriptor::get_test_full_name($test));
        }
    }
    protected function is_executed_in_current_environment(Test_Interface $test): bool
    {
        $envs = $test->get_metadata()->get_env();
        if ($envs === []) {
            return true;
        }
        $current = array_filter(array_map(trim(...), explode(',', $this->env)));
        foreach ($envs as $env_list) {
            $env_list = array_filter(array_map(trim(...), explode(',', (string) $env_list)));
            if ($env_list === [] || array_diff($env_list, $current) === []) {
                return true;
            }
        }
        return false;
    }
    protected function configure_test(Test_Interface $test): void
    {
        $di = clone $this->di;
        $test->get_metadata()->set_services(['di' => $di, 'dispatcher' => $this->dispatcher, 'modules' => $this->module_container]);
        $test->get_metadata()->set_current(['actor' => $this->get_actor_class_name(), 'env' => $this->env, 'modules' => $this->module_container->all()]);
        if ($test instanceof Test_Case_Wrapper) {
            $di->set(new Scenario($test));
            $test_case = $test->get_test_case();
            if ($test_case instanceof Unit) {
                $test_case->set_metadata($test->get_metadata());
            }
        }
        if ($test instanceof Scenario_Driven) {
            $test->preload();
        }
    }
    private function dispatch(string $event): void
    {
        $this->dispatcher->dispatch(new Event\Suite_Event($this->suite, $this->settings), $event);
    }
}
<?php

declare (strict_types=1);
namespace Codeception\Template;

use Codeception\Extension\Run_Failed;
use Codeception\Init_Template;
use Codeception\Module\Asserts;
use Codeception\Module\Php_Browser;
use Symfony\Component\Yaml\Yaml;
class Bootstrap extends Init_Template
{
    protected string $support_dir = 'tests/Support';
    protected string $data_dir = 'tests/Support/Data';
    protected string $envs_dir = 'tests/_envs';
    protected string $output_dir = 'tests/_output';
    protected string $namespace = 'Tests';
    protected string $support_namespace = 'Support';
    public function setup(): void
    {
        $this->check_installed($this->work_dir);
        $input = $this->input;
        if ($input->get_option('namespace')) {
            $this->namespace = trim((string) $input->get_option('namespace'), '\\');
        }
        if ($input->has_option('actor') && $input->get_option('actor')) {
            $this->actor_suffix = $input->get_option('actor');
        }
        $this->say("<fg=white;bg=magenta> Bootstrapping Codeception </fg=white;bg=magenta>\n");
        $this->create_global_config();
        $this->say('File codeception.yml created       <- global configuration');
        $this->create_dirs();
        if ($input->has_option('empty') && $input->get_option('empty')) {
            return;
        }
        if (!class_exists(Asserts::class) || !class_exists(Php_Browser::class)) {
            $this->add_modules_to_composer(['PhpBrowser', 'Asserts']);
        }
        $this->create_unit_suite();
        $this->create_functional_suite();
        $this->create_acceptance_suite();
        $this->say(' --- ');
        $this->say();
        $this->say_success('Codeception is installed for acceptance, functional, and unit testing');
        $this->say();
        $this->say('<bold>Next steps:</bold>');
        $this->say('1. Edit <bold>tests/Acceptance.suite.yml</bold> to set url of your application. Change PhpBrowser to WebDriver to enable browser testing');
        $this->say("2. Edit <bold>tests/Functional.suite.yml</bold> to enable a framework module. Remove this file if you don't use a framework");
        $this->say('3. Create your first acceptance tests using <comment>codecept g:cest Acceptance First</comment>');
        $this->say('4. Write first test in <bold>tests/Acceptance/FirstCest.php</bold>');
        $this->say('5. Run tests using: <comment>codecept run</comment>');
    }
    protected function create_dirs(): void
    {
        $this->create_directory_for('tests');
        $this->create_directory_for($this->output_dir);
        $this->create_empty_directory($this->data_dir);
        $this->create_directory_for($this->support_dir . DIRECTORY_SEPARATOR . '_generated');
        $this->create_directory_for($this->support_dir . DIRECTORY_SEPARATOR . 'Helper');
        $this->git_ignore($this->output_dir);
        $this->git_ignore($this->support_dir . DIRECTORY_SEPARATOR . '/_generated');
    }
    protected function create_functional_suite(string $actor = 'Functional'): void
    {
        $config = <<<EOF
        # Codeception Test Suite Configuration
        #
        # Suite for functional tests
        # Emulate web requests and make application process them
        # Include one of framework modules (Symfony, Yii2, Laravel, Phalcon5) to use it
        # Remove this suite if you don't use frameworks
        
        actor: {$actor}{$this->actor_suffix}
        modules:
            enabled:
                # add a framework module here
        step_decorators: ~
        
        EOF;
        $this->create_suite('Functional', $actor, $config);
        $this->say('tests/Functional/ created          <- functional tests');
        $this->say('tests/Functional.suite.yml written <- functional test suite configuration');
    }
    protected function create_acceptance_suite(string $actor = 'Acceptance'): void
    {
        $config = <<<EOF
        # Codeception Acceptance Test Suite Configuration
        #
        # Perform tests in a browser by either emulating one using PhpBrowser, or in a real browser using WebDriver.
        # If you need both WebDriver and PhpBrowser tests, create a separate suite for each.
        
        actor: {$actor}{$this->actor_suffix}
        modules:
            enabled:
                - PhpBrowser:
                    url: http://localhost/myapp
        # Add Codeception\\Step\\Retry trait to AcceptanceTester to enable retries
        step_decorators:
            - Codeception\\Step\\ConditionalAssertion
            - Codeception\\Step\\TryTo
            - Codeception\\Step\\Retry
        
        EOF;
        $this->create_suite('Acceptance', $actor, $config);
        $this->say('tests/Acceptance/ created          <- acceptance tests');
        $this->say('tests/Acceptance.suite.yml written <- acceptance test suite configuration');
    }
    protected function create_unit_suite(string $actor = 'Unit'): void
    {
        $config = <<<EOF
        # Codeception Test Suite Configuration
        #
        # Suite for unit or integration tests.
        
        actor: {$actor}{$this->actor_suffix}
        modules:
            enabled:
                - Asserts
        step_decorators: ~
        
        EOF;
        $this->create_suite('Unit', $actor, $config);
        $this->say('tests/Unit/ created                <- unit tests');
        $this->say('tests/Unit.suite.yml written       <- unit test suite configuration');
    }
    public function create_global_config(): void
    {
        $config = ['support_namespace' => $this->support_namespace, 'paths' => ['tests' => 'tests', 'output' => $this->output_dir, 'data' => $this->data_dir, 'support' => $this->support_dir, 'envs' => $this->envs_dir], 'actor_suffix' => 'Tester', 'extensions' => ['enabled' => [Run_Failed::class]]];
        $yaml = Yaml::dump($config, 4);
        if ($this->namespace) {
            $yaml = "namespace: {$this->namespace}\n" . $yaml;
        }
        $this->create_file('codeception.yml', $yaml);
    }
    protected function create_suite(string $name, string $actor, string $config): void
    {
        $settings = Yaml::parse($config);
        $settings['support_namespace'] = $this->support_namespace;
        $dir = 'tests' . DIRECTORY_SEPARATOR . $name;
        $this->create_directory_for($dir, "{$name}.suite.yml");
        $this->create_actor($actor . $this->actor_suffix, $this->support_dir, $settings);
        $this->create_file('tests' . DIRECTORY_SEPARATOR . "{$name}.suite.yml", $config);
    }
}
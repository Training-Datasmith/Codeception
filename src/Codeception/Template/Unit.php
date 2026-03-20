<?php

declare (strict_types=1);
namespace Codeception\Template;

use Codeception\Init_Template;
use Codeception\Template\Shared\Template_Helpers_Trait;
use Codeception\Util\Template;
use Symfony\Component\Yaml\Yaml;
class Unit extends Init_Template
{
    use Template_Helpers_Trait;
    protected string $config_template = <<<EOF
    suites:
        Unit:
            path: .
    {{tester}}
    settings:
        shuffle: true
        lint: true
    paths:
        tests: {{baseDir}}
        output: {{baseDir}}/_output
        support: {{baseDir}}/Support
        data: {{baseDir}}/Support/Data
         
    EOF;
    protected string $tester_and_modules = <<<EOF
            actor: UnitTester
            modules:
                enabled:
                    # add more modules here
                    - Asserts
            step_decorators: ~
    
    EOF;
    public function setup(): void
    {
        $this->say_info('This will install Codeception for unit testing only');
        $this->say();
        $dir = $this->ask('Where tests will be stored?', 'tests');
        if ($this->namespace === '') {
            $this->namespace = $this->ask('Enter a default namespace for tests (or skip this step)');
        }
        $this->say();
        $this->say('Codeception provides additional features for integration tests');
        $this->say('Like accessing frameworks, ORM, Database.');
        $have_tester = $this->ask('Do you wish to enable them?', false);
        $this->create_suite_dirs($dir);
        $this->say_info("Created test directory at {$dir}");
        $config = (new Template($this->config_template))->place('baseDir', $dir)->place('tester', $have_tester ? $this->tester_and_modules : '')->produce();
        $namespace = rtrim($this->namespace, '\\');
        $config = "namespace: {$namespace}\nsupport_namespace: {$this->support_namespace}\n" . $config;
        $this->create_file('codeception.yml', $config);
        $this->ensure_modules(['Asserts']);
        if ($have_tester) {
            $settings = Yaml::parse($config)['suites']['Unit'];
            $settings['support_namespace'] = $this->support_namespace;
            $this->create_actor('UnitTester', $dir . DIRECTORY_SEPARATOR . 'Support', $settings);
        }
        $this->say_success('INSTALLATION COMPLETE');
        $this->say();
        $this->say('Unit tests run in random order; use @depends to control.');
        if ($have_tester) {
            $this->say('To access DI, ORM, Database enable corresponding modules in codeception.yml');
            $this->say('Use <bold>$this->tester</bold> object inside Codeception\Test\Unit to call their methods');
            $this->say("For example: \$this->tester->seeInDatabase('users', ['name' => 'davert'])");
        }
        $this->say();
        $this->say('<bold>Next steps:</bold>');
        $this->say('1. Generate a test: <comment>codecept g:test unit MyTest</comment>');
        $this->say('2. Run tests: <comment>codecept run</comment>');
        $this->say('<bold>Happy testing!</bold>');
    }
}
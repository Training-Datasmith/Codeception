<?php

declare (strict_types=1);
namespace Codeception\Template;

use Codeception\Init_Template;
use Codeception\Template\Shared\Template_Helpers_Trait;
use Codeception\Util\Template;
use Symfony\Component\Yaml\Yaml;
class Acceptance extends Init_Template
{
    use Template_Helpers_Trait;
    protected string $config_template = <<<EOF
    # suite config
    suites:
        Acceptance:
            actor: AcceptanceTester
            path: .
            modules:
                enabled:
                    - WebDriver:
                        url: {{url}}
                        browser: {{browser}}
    
            # add Codeception\\Step\\Retry trait to AcceptanceTester to enable retries
            step_decorators:
                - Codeception\\Step\\ConditionalAssertion
                - Codeception\\Step\\TryTo
                - Codeception\\Step\\Retry
    
    extensions:
        enabled: [Codeception\\Extension\\RunFailed]
    
    params:
        - env
    
    gherkin: []
    
    # additional paths
    paths:
        tests: {{baseDir}}
        output: {{baseDir}}/_output
        data: {{baseDir}}/Support/Data
        support: {{baseDir}}/Support
        envs: {{baseDir}}/_envs
    
    settings:
        shuffle: false
        lint: true
    EOF;
    protected string $first_test = <<<EOF
    <?php
    
    namespace {{namespace}};
    
    use {{namespace}}\\{{support_namespace}}\\AcceptanceTester;
    
    class LoginCest
    {
        public function _before(AcceptanceTester \$I)
        {
            \$I->amOnPage('/');
        }
    
        public function loginSuccessfully(AcceptanceTester \$I)
        {
            // write a positive login test 
        }
    
        public function loginWithInvalidPassword(AcceptanceTester \$I)
        {
            // write a negative login test
        }
    }
    EOF;
    public function setup(): void
    {
        $this->check_installed();
        $this->say("Let's prepare Codeception for acceptance testing");
        $this->say('Create your tests and run them in real browser');
        $this->say();
        $dir = $this->ask('Where tests will be stored?', 'tests');
        $browser = $this->ask('Select a browser for testing', ['chrome', 'firefox']);
        $this->say_info($browser === 'chrome' ? 'Ensure Selenium Server and ChromeDriver are installed' : 'Ensure Selenium Server and GeckoDriver are installed');
        $url = $this->ask('Start URL for tests', 'http://localhost');
        $this->create_suite_dirs($dir);
        $this->say_info("Created test directories at {$dir}");
        $this->ensure_modules(['WebDriver']);
        $config = (new Template($this->config_template))->place('url', $url)->place('browser', $browser)->place('baseDir', $dir)->produce();
        $namespace = rtrim($this->namespace, '\\');
        $config = "namespace: {$namespace}\nsupport_namespace: {$this->support_namespace}\n" . $config;
        $this->create_file('codeception.yml', $config);
        $settings = Yaml::parse($config)['suites']['Acceptance'];
        $settings['support_namespace'] = $this->support_namespace;
        $this->create_actor('AcceptanceTester', $dir . DIRECTORY_SEPARATOR . 'Support', $settings);
        $this->say_info('Created global config codeception.yml inside the root directory');
        $first_test = (new Template($this->first_test))->place('namespace', $namespace)->place('support_namespace', $this->support_namespace)->produce();
        $this->create_file($dir . DIRECTORY_SEPARATOR . 'LoginCest.php', $first_test);
        $this->say_info('Created a demo test LoginCest.php');
        $this->say();
        $this->say_success('INSTALLATION COMPLETE');
        $this->say();
        $this->say('<bold>Next steps:</bold>');
        $this->say('1. Launch Selenium Server and webserver');
        $this->say("2. Edit <bold>{$dir}/LoginCest.php</bold> to test login of your application");
        $this->say('3. Run tests using: <comment>codecept run</comment>');
        $this->say();
        $this->say("HINT: Add '\\Codeception\\Step\\Retry' trait to AcceptanceTester class to enable auto-retries");
        $this->say('HINT: See https://codeception.com/docs/03-AcceptanceTests#retry');
        $this->say('<bold>Happy testing!</bold>');
    }
}
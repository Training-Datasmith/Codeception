<?php

declare (strict_types=1);
namespace Codeception\Template;

use Codeception\Init_Template;
use Codeception\Template\Shared\Template_Helpers_Trait;
use Codeception\Util\Template;
use Symfony\Component\Yaml\Yaml;
class Api extends Init_Template
{
    use Template_Helpers_Trait;
    protected string $config_template = <<<EOF
    # suite config
    suites:
        'Api:
            actor: ApiTester
            path: .
            modules:
                enabled:
                    - REST:
                        url: {{url}}
                        depends: PhpBrowser
            step_decorators:
                - \\Codeception\\Step\\AsJson
    
    paths:
        tests: {{baseDir}}
        output: {{baseDir}}/_output
        data: {{baseDir}}/Support/Data
        support: {{baseDir}}/Support
    
    settings:
        shuffle: false
        lint: true
    EOF;
    protected string $first_test = <<<EOF
    <?php
    
    namespace {{namespace}};
    
    use {{namespace}}\\{{support_namespace}}\\ApiTester;
    
    class ApiCest
    {
        public function tryApi(ApiTester \$I)
        {
            \$I->sendGet('/');
            \$I->seeResponseCodeIs(200);
            \$I->seeResponseIsJson();
        }
    }
    EOF;
    public function setup(): void
    {
        $this->check_installed();
        $this->say("Let's prepare Codeception for REST API testing");
        $this->say();
        $dir = $this->ask('Where tests will be stored?', 'tests');
        $url = $this->ask('Start URL for tests', 'http://localhost/api');
        $this->create_suite_dirs($dir);
        $this->say_info("Created test directories at {$dir}");
        $this->ensure_modules(['REST', 'PhpBrowser']);
        $config = (new Template($this->config_template))->place('url', $url)->place('baseDir', $dir)->produce();
        $namespace = rtrim($this->namespace, '\\');
        $config = "namespace: {$namespace}\nsupport_namespace: {$this->support_namespace}\n" . $config;
        $this->create_file('codeception.yml', $config);
        $settings = Yaml::parse($config)['suites']['Api'];
        $settings['support_namespace'] = $this->support_namespace;
        $this->create_actor('ApiTester', $dir . DIRECTORY_SEPARATOR . 'Support', $settings);
        $this->say_info('Created global config codeception.yml inside the root directory');
        $first_test = (new Template($this->first_test))->place('namespace', $namespace)->place('support_namespace', $this->support_namespace)->produce();
        $this->create_file($dir . DIRECTORY_SEPARATOR . 'ApiCest.php', $first_test);
        $this->say_info('Created a demo test ApiCest.php');
        $this->say();
        $this->say_success('INSTALLATION COMPLETE');
        $this->say();
        $this->say('<bold>Next steps:</bold>');
        $this->say("1. Edit <bold>{$dir}/ApiCest.php</bold> to write first API tests");
        $this->say('2. Run tests using: <comment>codecept run</comment>');
        $this->say();
        $this->say('<bold>Happy testing!</bold>');
    }
}
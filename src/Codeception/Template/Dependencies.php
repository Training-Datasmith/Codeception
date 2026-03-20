<?php

declare (strict_types=1);
namespace Codeception\Template;

use Codeception\Configuration;
use Codeception\Init_Template;
use Exception;
class Dependencies extends Init_Template
{
    /**
     * @var string
     */
    public const DONATE_LINK = 'https://opencollective.com/codeception';
    public function setup(): void
    {
        try {
            $this->check_installed();
        } catch (Exception) {
            $this->say_warning('Codeception is not installed in this directory.');
            return;
        }
        $this->say_info('Install Codeception Modules as Composer Packages');
        $this->say();
        $suites = Configuration::suites();
        if ($suites === []) {
            $this->say_error('No suites found in current config.');
            $this->say_warning('If you use sub-configs with `include` option, run this script on subconfigs:');
            $this->say_warning('Example: php vendor/bin/codecept init dependencies -c backend/');
            throw new Exception("No suites found, can't upgrade");
        }
        $modules = [];
        $config = Configuration::config();
        foreach ($suites as $suite) {
            $settings = Configuration::suite_settings($suite, $config);
            $modules = array_merge($modules, Configuration::modules($settings));
        }
        $num_packages = $this->add_modules_to_composer($modules);
        if ($num_packages === 0) {
            $this->say_warning('No change needed! Everything is installed');
            return;
        }
        $this->say_success('Done installing!');
        $this->say();
        $this->say('Please consider donating to Codeception on regular basis:');
        $this->say();
        $this->say('<bold>' . self::DONATE_LINK . '</bold>');
        $this->say();
        $this->say("It's ok to pay for reliable software.");
        $this->say('Talk to your manager & support further development of Codeception!');
    }
}
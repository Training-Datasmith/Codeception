<?php

declare (strict_types=1);
namespace Codeception\Command;

use Codeception\Template\Bootstrap as BootstrapTemplate;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
/**
 * Creates default config, tests directory and sample suites for current project.
 * Use this command to start building a test suite.
 *
 * By default, it will create 3 suites **Acceptance**, **Functional**, and **Unit**.
 *
 * * `codecept bootstrap` - creates `tests` dir and `codeception.yml` in current dir.
 * * `codecept bootstrap --empty` - creates `tests` dir without suites
 * * `codecept bootstrap --namespace Frontend` - creates tests, and use `Frontend` namespace for actor classes and helpers.
 * * `codecept bootstrap --actor Wizard` - sets actor as Wizard, to have `TestWizard` actor in tests.
 * * `codecept bootstrap path/to/the/project` - provide different path to a project, where tests should be placed
 *
 */
#[As_Command(name: 'bootstrap', description: 'Creates default test suites and generates all required files')]
class Bootstrap extends Command
{
    protected function configure(): void
    {
        $this->add_argument('path', Input_Argument::OPTIONAL, 'custom installation dir')->add_option('namespace', 's', Input_Option::VALUE_OPTIONAL, 'Namespace to add for actor classes and helpers')->add_option('actor', 'a', Input_Option::VALUE_OPTIONAL, 'Custom actor instead of Tester')->add_option('empty', 'e', Input_Option::VALUE_NONE, "Don't create standard suites");
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $bootstrap = new Bootstrap_Template($input, $output);
        if ($path = $input->get_argument('path')) {
            $bootstrap->init_dir($path);
        }
        $bootstrap->setup();
        return Command::SUCCESS;
    }
}
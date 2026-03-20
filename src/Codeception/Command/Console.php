<?php

declare (strict_types=1);
namespace Codeception\Command;

use function array_keys;
use Codeception\Codecept;
use Codeception\Configuration;
use Codeception\Event\Suite_Event;
use Codeception\Event\Test_Event;
use Codeception\Events;
use Codeception\Exception\Configuration_Exception;
use Codeception\Lib\Console\Output;
use Codeception\Scenario;
use Codeception\Suite;
use Codeception\Suite_Manager;
use Codeception\Test\Cept;
use Codeception\Util\Debug;
use function file_exists;
use function function_exists;
use function pcntl_signal;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
/**
 * Try to execute test commands in run-time. You may try commands before writing the test.
 *
 * * `codecept console Acceptance` - starts acceptance suite environment. If you use WebDriver you can manipulate browser with Codeception commands.
 */
#[As_Command(name: 'console', description: 'Launches interactive test console')]
class Console extends Command
{
    protected ?Cept $test = null;
    protected ?Codecept $codecept = null;
    protected ?Suite $suite = null;
    protected ?Output_Interface $output = null;
    /**
     * @var string[]
     */
    protected array $actions = [];
    protected function configure(): void
    {
        $this->add_argument('suite', Input_Argument::REQUIRED, 'suite to be executed')->add_option('colors', null, Input_Option::VALUE_NONE, 'Use colors in output');
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $suite_name = $input->get_argument('suite');
        $this->output = $output;
        $config = Configuration::config();
        $settings = Configuration::suite_settings($suite_name, $config);
        $options = $input->get_options();
        $options['debug'] = true;
        $options['silent'] = true;
        $options['interactive'] = false;
        $options['colors'] = true;
        Debug::set_output(new Output($options));
        $this->codecept = new Codecept($options);
        $event_dispatcher = $this->codecept->get_dispatcher();
        $suite_manager = new Suite_Manager($event_dispatcher, $suite_name, $settings, []);
        $suite_manager->initialize();
        $this->suite = $suite_manager->get_suite();
        $module_container = $suite_manager->get_module_container();
        $this->actions = array_keys($module_container->get_actions());
        $this->test = new Cept('', '');
        $this->test->get_metadata()->set_services(['dispatcher' => $event_dispatcher, 'modules' => $module_container]);
        $scenario = new Scenario($this->test);
        if (!$settings['actor']) {
            throw new Configuration_Exception("Interactive shell can't be started without an actor");
        }
        if (isset($config['namespace']) && $config['namespace'] !== '') {
            $settings['actor'] = $config['namespace'] . '\Support\\' . $settings['actor'];
        }
        $actor = $settings['actor'];
        $I = new $actor($scenario);
        $this->listen_to_signals();
        $output->writeln("<info>Interactive console started for suite {$suite_name}</info>");
        $output->writeln('<info>Try Codeception commands without writing a test</info>');
        $suite_event = new Suite_Event($this->suite, $settings);
        $event_dispatcher->dispatch($suite_event, Events::SUITE_INIT);
        $event_dispatcher->dispatch(new Test_Event($this->test), Events::TEST_PARSED);
        $event_dispatcher->dispatch(new Test_Event($this->test), Events::TEST_BEFORE);
        if (is_string($settings['bootstrap']) && file_exists($settings['bootstrap'])) {
            require $settings['bootstrap'];
        }
        $I->pause();
        $event_dispatcher->dispatch(new Test_Event($this->test), Events::TEST_AFTER);
        $event_dispatcher->dispatch(new Suite_Event($this->suite), Events::SUITE_AFTER);
        $output->writeln('<info>Bye-bye!</info>');
        return Command::SUCCESS;
    }
    protected function listen_to_signals(): void
    {
        if (function_exists('pcntl_signal')) {
            declare (ticks=1);
            pcntl_signal(SIGINT, SIG_IGN);
            pcntl_signal(SIGTERM, SIG_IGN);
        }
    }
}
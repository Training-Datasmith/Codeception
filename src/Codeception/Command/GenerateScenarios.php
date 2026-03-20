<?php

declare (strict_types=1);
namespace Codeception\Command;

use function basename;
use Codeception\Configuration;
use Codeception\Exception\Configuration_Exception;
use Codeception\Suite_Manager;
use Codeception\Test\Cest;
use Codeception\Test\Interfaces\Descriptive;
use Codeception\Test\Interfaces\Scenario_Driven;
use function file_exists;
use function is_writable;
use function mkdir;
use function preg_replace;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Event_Dispatcher\Event_Dispatcher;
/**
 * Generates user-friendly text scenarios from scenario-driven tests (Cest).
 *
 * * `codecept g:scenarios Acceptance` - for all acceptance tests
 * * `codecept g:scenarios Acceptance --format html` - in html format
 * * `codecept g:scenarios Acceptance --path doc` - generate scenarios to `doc` dir
 */
#[As_Command(name: 'generate:scenarios', description: 'Generates text representation for all scenarios')]
class Generate_Scenarios extends Command
{
    use Shared\File_System_Trait;
    use Shared\Config_Trait;
    protected function configure(): void
    {
        $this->add_argument('suite', Input_Argument::REQUIRED, 'suite from which texts should be generated')->add_option('path', 'p', Input_Option::VALUE_REQUIRED, 'Use specified path as destination instead of default')->add_option('single-file', '', Input_Option::VALUE_NONE, 'Render all scenarios to only one file')->add_option('format', 'f', Input_Option::VALUE_REQUIRED, 'Specify output format: html or text (default)', 'text');
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $suite = $input->get_argument('suite');
        $suite_conf = $this->get_suite_config($suite);
        $path = $input->get_option('path') ?: Configuration::data_dir() . 'scenarios';
        $format = $input->get_option('format');
        @mkdir($path, 0777, true);
        if (!is_writable($path)) {
            throw new Configuration_Exception("Path {$path} is not writable. Please, set valid permissions for folder to store scenarios.");
        }
        $path .= DIRECTORY_SEPARATOR . $suite;
        if (!$input->get_option('single-file')) {
            @mkdir($path);
        }
        $suite_manager = new Suite_Manager(new Event_Dispatcher(), $suite, $suite_conf, []);
        if ($suite_conf['bootstrap'] && file_exists($suite_conf['path'] . $suite_conf['bootstrap'])) {
            require_once $suite_conf['path'] . $suite_conf['bootstrap'];
        }
        $tests = $this->get_tests($suite_manager);
        $scenarios = '';
        $output->writeln('<comment>This command is deprecated and will be removed in the next major version of Codeception.</comment>');
        foreach ($tests as $test) {
            if (!$test instanceof Scenario_Driven) {
                continue;
            }
            if (!$test instanceof Descriptive) {
                continue;
            }
            $feature = $test->get_scenario_text($format);
            $name = $this->underscore(basename($test->get_file_name(), '.php'));
            // create separate file for each test in Cest
            if ($test instanceof Cest && !$input->get_option('single-file')) {
                $name .= '.' . $this->underscore($test->get_test_method());
            }
            if ($input->get_option('single-file')) {
                $scenarios .= $feature;
                $output->writeln("* {$name} rendered");
            } else {
                $feature = $this->decorate($feature, $format);
                $this->create_file($path . DIRECTORY_SEPARATOR . $name . $this->format_extension($format), $feature, true);
                $output->writeln("* {$name} generated");
            }
        }
        if ($input->get_option('single-file')) {
            $this->create_file($path . $this->format_extension($format), $this->decorate($scenarios, $format), true);
        }
        return Command::SUCCESS;
    }
    protected function decorate(string $text, string $format): string
    {
        if ($format === 'html') {
            return "<html><body>{$text}</body></html>";
        }
        return $text;
    }
    protected function get_tests($suite_manager)
    {
        $suite_manager->load_tests();
        return $suite_manager->get_suite()->get_tests();
    }
    protected function format_extension(string $format): string
    {
        return '.' . ($format === 'html' ? 'html' : 'txt');
    }
    private function underscore(string $name): string
    {
        $name = preg_replace('#([A-Z]+)([A-Z][a-z])#', '\1_\2', $name);
        $name = preg_replace('#([a-z\d])([A-Z])#', '\1_\2', (string) $name);
        $name = str_replace(['/', '\\'], ['.', '.'], $name);
        $name = preg_replace('#_Cept$#', '', $name);
        return preg_replace('#_Cest$#', '', (string) $name);
    }
}
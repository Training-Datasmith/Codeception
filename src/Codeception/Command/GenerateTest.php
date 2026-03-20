<?php

declare (strict_types=1);
namespace Codeception\Command;

use Codeception\Lib\Generator\Test as TestGenerator;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
/**
 * Generates skeleton for Unit Test that extends `Codeception\TestCase\Test`.
 *
 * * `codecept g:test Unit User`
 * * `codecept g:test Unit "App\User"`
 */
#[As_Command(name: 'generate:test', description: 'Generates empty unit test file in suite')]
class Generate_Test extends Command
{
    use Shared\File_System_Trait;
    use Shared\Config_Trait;
    protected function configure(): void
    {
        $this->add_argument('suite', Input_Argument::REQUIRED, 'Suite where tests will be put')->add_argument('class', Input_Argument::REQUIRED, 'Class name');
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $suite = $input->get_argument('suite');
        $class = $input->get_argument('class');
        $config = $this->get_suite_config($suite);
        $class_name = $this->get_short_class_name($class);
        $path = $this->create_directory_for($config['path'], $class);
        $filename = $path . $this->complete_suffix($class_name, 'Test');
        $test = new Test_Generator($config, $class);
        $res = $this->create_file($filename, $test->produce());
        if (!$res) {
            $output->writeln("<error>Test {$filename} already exists</error>");
            return Command::FAILURE;
        }
        $output->writeln("<info>Test was created in {$filename}</info>");
        return Command::SUCCESS;
    }
}
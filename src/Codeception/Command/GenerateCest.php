<?php

declare (strict_types=1);
namespace Codeception\Command;

use Codeception\Lib\Generator\Cest as CestGenerator;
use function file_exists;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
/**
 * Generates Cest (scenario-driven object-oriented test) file:
 *
 * * `codecept generate:cest suite Login`
 * * `codecept g:cest suite subdir/subdir/testnameCest.php`
 * * `codecept g:cest suite LoginCest -c path/to/project`
 * * `codecept g:cest "App\Login"`
 *
 */
#[As_Command(name: 'generate:cest', description: 'Generates empty Cest file in suite')]
class Generate_Cest extends Command
{
    use Shared\File_System_Trait;
    use Shared\Config_Trait;
    protected function configure(): void
    {
        $this->add_argument('suite', Input_Argument::REQUIRED, 'suite where tests will be put')->add_argument('class', Input_Argument::REQUIRED, 'test name');
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $suite = $input->get_argument('suite');
        $class = $input->get_argument('class');
        $config = $this->get_suite_config($suite);
        $class_name = $this->get_short_class_name($class);
        $path = $this->create_directory_for($config['path'], $class);
        $filename = $this->complete_suffix($class_name, 'Cest');
        $filename = $path . $filename;
        if (file_exists($filename)) {
            $output->writeln("<error>Test {$filename} already exists</error>");
            return Command::FAILURE;
        }
        $cest = new Cest_Generator($class, $config);
        $res = $this->create_file($filename, $cest->produce());
        if (!$res) {
            $output->writeln("<error>Test {$filename} already exists</error>");
            return Command::FAILURE;
        }
        $output->writeln("<info>Test was created in {$filename}</info>");
        return Command::SUCCESS;
    }
}
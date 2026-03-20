<?php

declare (strict_types=1);
namespace Codeception\Command;

use function basename;
use Codeception\Lib\Generator\Feature;
use function preg_match;
use function rtrim;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
/**
 * Generates Feature file (in Gherkin):
 *
 * * `codecept generate:feature suite Login`
 * * `codecept g:feature suite subdir/subdir/login.feature`
 * * `codecept g:feature suite login.feature -c path/to/project`
 *
 */
#[As_Command(name: 'generate:feature', description: 'Generates empty feature file in suite')]
class Generate_Feature extends Command
{
    use Shared\File_System_Trait;
    use Shared\Config_Trait;
    protected function configure(): void
    {
        $this->add_argument('suite', Input_Argument::REQUIRED, 'suite to be tested')->add_argument('feature', Input_Argument::REQUIRED, 'feature to be generated')->add_option('config', 'c', Input_Option::VALUE_OPTIONAL, 'Use custom path for config');
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $suite = $input->get_argument('suite');
        $filename = (string) $input->get_argument('feature');
        $config = $this->get_suite_config($suite);
        $this->create_directory_for($config['path'], $filename);
        $feature = new Feature(basename($filename));
        if (!preg_match('#\.feature$#', $filename)) {
            $filename .= '.feature';
        }
        $full_path = rtrim((string) $config['path'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
        $res = $this->create_file($full_path, $feature->produce());
        if (!$res) {
            $output->writeln("<error>Feature {$filename} already exists</error>");
            return Command::FAILURE;
        }
        $output->writeln("<info>Feature was created in {$full_path}</info>");
        return Command::SUCCESS;
    }
}
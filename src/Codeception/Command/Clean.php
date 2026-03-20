<?php

declare (strict_types=1);
namespace Codeception\Command;

use Codeception\Configuration;
use Codeception\Util\File_System;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
/**
 * Recursively cleans `output` directory and generated code.
 *
 * * `codecept clean`
 *
 */
#[As_Command(name: 'clean', description: 'Recursively cleans log and generated code')]
class Clean extends Command
{
    use Shared\Config_Trait;
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $this->clean_projects_recursively($output, Configuration::project_dir());
        $output->writeln('Done');
        return Command::SUCCESS;
    }
    private function clean_projects_recursively(Output_Interface $output, string $project_dir): void
    {
        $config = Configuration::config($project_dir);
        $log_dir = Configuration::output_dir();
        $output->writeln(sprintf('<info>Cleaning up output %s...</info>', $log_dir));
        File_System::do_empty_dir($log_dir);
        $sub_projects = $config['include'];
        foreach ($sub_projects as $sub_project) {
            $this->clean_projects_recursively($output, $project_dir . $sub_project);
        }
    }
}
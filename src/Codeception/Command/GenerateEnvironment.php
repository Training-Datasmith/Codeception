<?php

declare (strict_types=1);
namespace Codeception\Command;

use Codeception\Configuration;
use Codeception\Exception\Configuration_Exception;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
/**
 * Generates empty environment configuration file into envs dir:
 *
 *  * `codecept g:env firefox`
 *
 * Required to have `envs` path to be specified in `codeception.yml`
 */
#[As_Command(name: 'generate:environment', description: 'Generates empty environment config')]
class Generate_Environment extends Command
{
    use Shared\File_System_Trait;
    use Shared\Config_Trait;
    protected function configure(): void
    {
        $this->add_argument('env', Input_Argument::REQUIRED, 'Environment name');
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $config = $this->get_global_config();
        if (Configuration::envs_dir() === '') {
            throw new Configuration_Exception("Path for environments configuration is not set.\n" . "Please specify envs path in your `codeception.yml`\n \n" . 'envs: tests/_envs');
        }
        $relative_path = $config['paths']['envs'];
        $env = $input->get_argument('env');
        $file = $env . '.yml';
        $path = $this->create_directory_for($relative_path, $file);
        $saved = $this->create_file($path . $file, sprintf('# `%s` environment config goes here', $env));
        if ($saved) {
            $output->writeln(sprintf('<info>%s config was created in %s/%s</info>', $env, $relative_path, $file));
            return Command::SUCCESS;
        }
        $output->writeln(sprintf('<error>File %s/%s already exists</error>', $relative_path, $file));
        return Command::FAILURE;
    }
}
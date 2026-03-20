<?php

declare (strict_types=1);
namespace Codeception\Command;

use Codeception\Configuration;
use Codeception\Lib\Generator\Group as GroupGenerator;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
use function ucfirst;
/**
 * Creates empty GroupObject - extension which handles all group events.
 *
 * * `codecept g:group Admin`
 */
#[As_Command(name: 'generate:groupobject', description: 'Generates Group subscriber')]
class Generate_Group extends Command
{
    use Shared\File_System_Trait;
    use Shared\Config_Trait;
    protected function configure(): void
    {
        $this->add_argument('group', Input_Argument::REQUIRED, 'Group class name');
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $config = $this->get_global_config();
        $group_input_argument = (string) $input->get_argument('group');
        $class = ucfirst($group_input_argument);
        $path = $this->create_directory_for(Configuration::support_dir() . 'Group' . DIRECTORY_SEPARATOR, $class);
        $filename = $path . $class . '.php';
        $group = new Group_Generator($config, $group_input_argument);
        $res = $this->create_file($filename, $group->produce());
        if (!$res) {
            $output->writeln("<error>Group {$filename} already exists</error>");
            return Command::FAILURE;
        }
        $output->writeln("<info>Group extension was created in {$filename}</info>");
        $output->writeln('To use this group extension, include it to "extensions" option of global Codeception config.');
        return Command::SUCCESS;
    }
}
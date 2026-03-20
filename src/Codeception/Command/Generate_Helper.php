<?php

declare (strict_types=1);
namespace Codeception\Command;

use Codeception\Configuration;
use Codeception\Lib\Generator\Helper;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
use function ucfirst;
/**
 * Creates empty Helper class.
 *
 * * `codecept g:helper MyHelper`
 * * `codecept g:helper "My\Helper"`
 */
#[As_Command(name: 'generate:helper', description: 'Generates a new helper')]
class Generate_Helper extends Command
{
    use Shared\File_System_Trait;
    use Shared\Config_Trait;
    protected function configure(): void
    {
        $this->add_argument('name', Input_Argument::REQUIRED, 'Helper name');
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $name = ucfirst((string) $input->get_argument('name'));
        $config = $this->get_global_config();
        $path = $this->create_directory_for(Configuration::support_dir() . 'Helper', $name);
        $filename = $path . $this->get_short_class_name($name) . '.php';
        $res = $this->create_file($filename, (new Helper($config, $name))->produce());
        if ($res) {
            $output->writeln("<info>Helper {$filename} created</info>");
            return Command::SUCCESS;
        }
        $output->writeln(sprintf('<error>Error creating helper %s</error>', $filename));
        return Command::FAILURE;
    }
}
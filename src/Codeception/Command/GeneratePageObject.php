<?php

declare (strict_types=1);
namespace Codeception\Command;

use Codeception\Configuration;
use Codeception\Lib\Generator\Page_Object as PageObjectGenerator;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
use function ucfirst;
/**
 * Generates PageObject. Can be generated either globally, or just for one suite.
 * If PageObject is generated globally it will act as UIMap, without any logic in it.
 *
 * * `codecept g:page Login`
 * * `codecept g:page Registration`
 * * `codecept g:page Acceptance Login`
 */
#[As_Command(name: 'generate:pageobject', description: 'Generates empty PageObject class')]
class Generate_Page_Object extends Command
{
    use Shared\File_System_Trait;
    use Shared\Config_Trait;
    protected function configure(): void
    {
        $this->add_argument('suite', Input_Argument::REQUIRED, 'Either suite name or page object name')->add_argument('page', Input_Argument::OPTIONAL, 'Page name of pageobject to represent');
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $suite = (string) $input->get_argument('suite');
        $class = $input->get_argument('page');
        if (!$class) {
            $class = $suite;
            $suite = '';
        }
        $conf = $suite ? $this->get_suite_config($suite) : $this->get_global_config();
        if ($suite) {
            $suite = DIRECTORY_SEPARATOR . ucfirst($suite);
        }
        $path = $this->create_directory_for(Configuration::support_dir() . 'Page' . $suite, $class);
        $filename = $path . $this->get_short_class_name($class) . '.php';
        $output->writeln($filename);
        $page_object = new Page_Object_Generator($conf, ucfirst($suite) . '\\' . $class);
        $res = $this->create_file($filename, $page_object->produce());
        if (!$res) {
            $output->writeln("<error>PageObject {$filename} already exists</error>");
            return Command::FAILURE;
        }
        $output->writeln("<info>PageObject was created in {$filename}</info>");
        return Command::SUCCESS;
    }
}
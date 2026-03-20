<?php

declare (strict_types=1);
namespace Codeception\Command;

use function class_exists;
use Codeception\Init_Template;
use Exception;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
use function ucfirst;
#[As_Command(name: 'init', description: 'Creates test suites by a template')]
class Init extends Command
{
    protected function configure(): void
    {
        $this->add_argument('template', Input_Argument::REQUIRED, 'Init template for the setup')->add_option('path', null, Input_Option::VALUE_REQUIRED, 'Change current directory')->add_option('namespace', null, Input_Option::VALUE_OPTIONAL, 'Namespace to add for actor classes and helpers');
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $template = (string) $input->get_argument('template');
        $class_name = class_exists($template) ? $template : 'Codeception\Template\\' . ucfirst($template);
        if (!class_exists($class_name)) {
            throw new Exception("Template from a {$class_name} can't be loaded; Init can't be executed");
        }
        $init_process = new $class_name($input, $output);
        if (!$init_process instanceof Init_Template) {
            throw new Exception($class_name . ' is not a valid template');
        }
        if ($path = $input->get_option('path')) {
            $init_process->init_dir($path);
        }
        $init_process->setup();
        return Command::SUCCESS;
    }
}
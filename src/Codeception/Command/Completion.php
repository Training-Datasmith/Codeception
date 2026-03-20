<?php

declare (strict_types=1);
namespace Codeception\Command;

use Codeception\Configuration;
use Stecman\Component\Symfony\Console\Bash_Completion\Completion as ConsoleCompletion;
use Stecman\Component\Symfony\Console\Bash_Completion\Completion\Completion_Interface as ConsoleCompletionInterface;
use Stecman\Component\Symfony\Console\Bash_Completion\Completion\Shell_Path_Completion;
use Stecman\Component\Symfony\Console\Bash_Completion\Completion_Command;
use Stecman\Component\Symfony\Console\Bash_Completion\Completion_Handler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Definition as SymfonyInputDefinition;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols
if (!class_exists(Console_Completion::class)) {
    echo "Please install `stecman/symfony-console-completion\n` to enable auto completion";
    return;
}
class Completion extends Completion_Command
{
    protected function configure_completion(Completion_Handler $handler): void
    {
        // Can't set for all commands, because it wouldn't work well with generate:suite
        $suite_commands = ['run', 'config:validate', 'console', 'dry-run', 'generate:cest', 'generate:feature', 'generate:phpunit', 'generate:scenarios', 'generate:stepobject', 'generate:test', 'gherkin:snippets', 'gherkin:steps'];
        foreach ($suite_commands as $suite_command) {
            $handler->add_handler(new Console_Completion($suite_command, 'suite', Console_Completion_Interface::TYPE_ARGUMENT, Configuration::suites()));
        }
        $handler->add_handlers([new Shell_Path_Completion(Console_Completion_Interface::ALL_COMMANDS, 'path', Console_Completion_Interface::TYPE_ARGUMENT), new Shell_Path_Completion(Console_Completion_Interface::ALL_COMMANDS, 'test', Console_Completion_Interface::TYPE_ARGUMENT)]);
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        if ($input->get_option('generate-hook') && $input->get_option('use-vendor-bin')) {
            global $argv;
            $argv[0] = 'vendor/bin/' . basename((string) $argv[0]);
        }
        parent::execute($input, $output);
        return Command::SUCCESS;
    }
    protected function create_definition(): Symfony_Input_Definition
    {
        $definition = parent::create_definition();
        $definition->add_option(new Input_Option('use-vendor-bin', null, Input_Option::VALUE_NONE, 'Use the vendor bin for autocompletion.'));
        return $definition;
    }
}
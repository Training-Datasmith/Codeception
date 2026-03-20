<?php

declare (strict_types=1);
namespace Codeception\Command;

use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
#[As_Command(name: '_completion', description: 'BASH completion hook.', hidden: true)]
class Completion_Fallback extends Command
{
    protected function configure(): void
    {
        $this->set_help(<<<END
        To enable BASH completion, install optional stecman/symfony-console-completion first:
        
            <comment>composer require stecman/symfony-console-completion</comment>
        
        END);
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $output->writeln('Install optional <comment>stecman/symfony-console-completion</comment>');
        return Command::SUCCESS;
    }
}
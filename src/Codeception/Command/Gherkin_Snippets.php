<?php

declare (strict_types=1);
namespace Codeception\Command;

use Codeception\Lib\Generator\Gherkin_Snippets as GherkinSnippetsGenerator;
use function count;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
/**
 * Generates code snippets for matched feature files in a suite.
 * Code snippets are expected to be implemented in Actor or PageObjects
 *
 * Usage:
 *
 * * `codecept gherkin:snippets Acceptance` - snippets from all feature of acceptance tests
 * * `codecept gherkin:snippets Acceptance/feature/users` - snippets from `feature/users` dir of acceptance tests
 * * `codecept gherkin:snippets Acceptance user_account.feature` - snippets from a single feature file
 * * `codecept gherkin:snippets Acceptance/feature/users/user_accout.feature` - snippets from feature file in a dir
 */
#[As_Command(name: 'gherkin:snippets', description: 'Fetches empty steps from feature files of suite and prints code snippets for them')]
class Gherkin_Snippets extends Command
{
    use Shared\Config_Trait;
    use Shared\Style_Trait;
    protected function configure(): void
    {
        $this->add_argument('suite', Input_Argument::REQUIRED, 'Suite to scan for feature files')->add_argument('test', Input_Argument::OPTIONAL, 'Test to be scanned')->add_option('config', 'c', Input_Option::VALUE_OPTIONAL, 'Use custom path for config');
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $this->add_styles($output);
        $suite = $input->get_argument('suite');
        $test = $input->get_argument('test');
        $config = $this->get_suite_config($suite);
        $generator = new Gherkin_Snippets_Generator($config, $test);
        $snippets = $generator->get_snippets();
        if ($snippets === []) {
            $output->writeln('<notice> All Gherkin steps are defined. Exiting... </notice>');
            return Command::SUCCESS;
        }
        $output->writeln('<comment> Snippets found in: </comment>');
        foreach ($generator->get_features() as $feature) {
            $output->writeln("<info>  - {$feature} </info>");
        }
        $output->writeln('<comment> Generated Snippets: </comment>');
        $output->writeln('<info> ----------------------------------------- </info>');
        foreach ($snippets as $snippet) {
            $output->writeln($snippet);
        }
        $output->writeln('<info> ----------------------------------------- </info>');
        $output->writeln(sprintf(' <bold>%d</bold> snippets proposed', count($snippets)));
        $output->writeln("<notice> Copy generated snippets to {$config['actor']} or a specific Gherkin context </notice>");
        return Command::SUCCESS;
    }
}
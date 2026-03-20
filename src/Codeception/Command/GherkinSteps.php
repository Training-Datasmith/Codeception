<?php

declare (strict_types=1);
namespace Codeception\Command;

use Codeception\Test\Loader\Gherkin as GherkinLoader;
use function count;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
/**
 * Prints all steps from all Gherkin contexts for a specific suite
 *
 * ```
 * codecept gherkin:steps Acceptance
 * ```
 */
#[As_Command(name: 'gherkin:steps', description: 'Prints all defined feature steps')]
class Gherkin_Steps extends Command
{
    use Shared\Config_Trait;
    use Shared\Style_Trait;
    protected function configure(): void
    {
        $this->add_argument('suite', Input_Argument::REQUIRED, 'suite to scan for feature files')->add_option('config', 'c', Input_Option::VALUE_OPTIONAL, 'Use custom path for config');
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $this->add_styles($output);
        $suite = $input->get_argument('suite');
        $config = $this->get_suite_config($suite);
        $config['describe_steps'] = true;
        $loader = new Gherkin_Loader($config);
        $steps = $loader->get_steps();
        foreach ($steps as $name => $context) {
            $table = new Table($output);
            $table->set_headers(['Step', 'Implementation']);
            $output->writeln("Steps from <bold>{$name}</bold> context:");
            foreach ($context as $step => $callable) {
                if (count($callable) >= 2) {
                    $method = $callable[0] . '::' . $callable[1];
                    $table->add_row([$step, $method]);
                }
            }
            $table->render();
        }
        if (!isset($table)) {
            $output->writeln('No steps are defined, start creating them by running <bold>gherkin:snippets</bold>');
        }
        return Command::SUCCESS;
    }
}
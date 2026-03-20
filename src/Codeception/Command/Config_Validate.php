<?php

declare (strict_types=1);
namespace Codeception\Command;

use function codecept_data_dir;
use function codecept_output_dir;
use function codecept_root_dir;
use Codeception\Configuration;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
/**
 * Validates and prints Codeception config.
 * Use it do debug Yaml configs
 *
 * Check config:
 *
 * * `codecept config`: check global config
 * * `codecept config Unit`: check suite config
 *
 * Load config:
 *
 * * `codecept config:validate -c path/to/another/config`: from another dir
 * * `codecept config:validate -c another_config.yml`: from another config file
 *
 * Check overriding config values (like in `run` command)
 *
 * * `codecept config:validate -o "settings: shuffle: true"`: enable shuffle
 * * `codecept config:validate -o "settings: lint: false"`: disable linting
 * * `codecept config:validate -o "reporters: report: \Custom\Reporter" --report`: use custom reporter
 *
 */
#[As_Command(name: 'config:validate', description: 'Validates and prints Codeception config')]
class Config_Validate extends Command
{
    use Shared\Config_Trait;
    use Shared\Style_Trait;
    protected function configure(): void
    {
        $this->add_argument('suite', Input_Argument::OPTIONAL, 'To show suite configuration')->add_option('config', 'c', Input_Option::VALUE_OPTIONAL, 'Use custom path for config')->add_option('override', 'o', Input_Option::VALUE_IS_ARRAY | Input_Option::VALUE_REQUIRED, 'Override config values');
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $this->add_styles($output);
        if ($suite = $input->get_argument('suite')) {
            $output->write("Validating <bold>{$suite}</bold> config... ");
            $config = $this->get_suite_config($suite);
            $output->writeln('Ok');
            $output->writeln("------------------------------\n");
            $output->writeln("<info>{$suite} Suite Config</info>:\n");
            $output->writeln($this->format_output($config));
            return Command::SUCCESS;
        }
        $output->write('Validating global config... ');
        $config = $this->get_global_config();
        $output->writeln($input->get_option('override'));
        if (!empty($input->get_option('override'))) {
            $config = $this->override_config($input->get_option('override'));
        }
        $output->writeln('Ok');
        $suites = Configuration::suites();
        $output->writeln("------------------------------\n");
        $output->writeln("<info>Codeception Config</info>:\n");
        $output->writeln($this->format_output($config));
        $output->writeln('<info>Directories</info>:');
        $output->writeln('<comment>codecept_root_dir()</comment>   ' . codecept_root_dir());
        $output->writeln('<comment>codecept_output_dir()</comment> ' . codecept_output_dir());
        $output->writeln('<comment>codecept_data_dir()</comment>   ' . codecept_data_dir());
        $output->writeln('');
        $output->writeln('<info>Available suites</info>: ' . implode(', ', $suites));
        foreach ($suites as $suite) {
            $output->write("Validating suite <bold>{$suite}</bold>... ");
            $this->get_suite_config($suite);
            $output->writeln('Ok');
        }
        $output->writeln('Execute <info>codecept config:validate [<suite>]</info> to see config for a suite');
        return Command::SUCCESS;
    }
    protected function format_output($config): ?string
    {
        $output = print_r($config, true);
        return preg_replace('#\[(.*?)] =>#', '<fg=yellow>$1</fg=yellow> =>', $output);
    }
}
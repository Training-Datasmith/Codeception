<?php

declare (strict_types=1);
namespace Codeception\Command;

use Codeception\Configuration;
use Codeception\Lib\Generator\Snapshot as SnapshotGenerator;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
use function ucfirst;
/**
 * Generates Snapshot.
 * Snapshot can be used to test dynamical data.
 * If suite name is provided, an actor class will be included into placeholder
 *
 * * `codecept g:snapshot UserEmails`
 * * `codecept g:snapshot Products`
 * * `codecept g:snapshot Acceptance UserEmails`
 */
#[As_Command(name: 'generate:snapshot', description: 'Generates empty Snapshot class')]
class Generate_Snapshot extends Command
{
    use Shared\File_System_Trait;
    use Shared\Config_Trait;
    protected function configure(): void
    {
        $this->add_argument('suite', Input_Argument::REQUIRED, 'Suite name or snapshot name')->add_argument('snapshot', Input_Argument::OPTIONAL, 'Name of snapshot');
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $suite = (string) $input->get_argument('suite');
        $class = $input->get_argument('snapshot');
        if (!$class) {
            $class = $suite;
            $suite = '';
        }
        $conf = $suite ? $this->get_suite_config($suite) : $this->get_global_config();
        if ($suite) {
            $suite = DIRECTORY_SEPARATOR . ucfirst($suite);
        }
        $path = $this->create_directory_for(Configuration::support_dir() . 'Snapshot' . $suite, $class);
        $filename = $path . $this->get_short_class_name($class) . '.php';
        $output->writeln($filename);
        $snapshot = new Snapshot_Generator($conf, ucfirst($suite) . '\\' . $class);
        $res = $this->create_file($filename, $snapshot->produce());
        if (!$res) {
            $output->writeln("<error>Snapshot {$filename} already exists</error>");
            return Command::FAILURE;
        }
        $output->writeln("<info>Snapshot was created in {$filename}</info>");
        return Command::SUCCESS;
    }
}
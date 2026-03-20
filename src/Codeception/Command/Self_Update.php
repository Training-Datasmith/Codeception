<?php

declare (strict_types=1);
namespace Codeception\Command;

use Codeception\Codecept;
use Exception;
use Humbug\Self_Update\Updater;
use Phar;
use function sprintf;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
/**
 * Auto-updates phar archive from official site: 'https://codeception.com/codecept.phar' .
 *
 * * `php codecept.phar self-update`
 *
 * @author Franck Cassedanne <franck@cassedanne.com>
 */
#[As_Command(name: 'self-update', aliases: ['selfupdate'])]
class Self_Update extends Command
{
    /**
     * @var string
     */
    public const NAME = 'Codeception';
    /**
     * @var string
     */
    public const GITHUB_REPO = 'Codeception/Codeception';
    /**
     * @var string
     */
    public const PHAR_URL = 'https://codeception.com/php80/';
    /**
     * Holds the current script filename.
     */
    protected string $filename;
    protected function configure(): void
    {
        $this->filename = $_SERVER['argv'][0] ?? Phar::running(false);
        $this->set_description(sprintf('Upgrade <comment>%s</comment> to the latest version', $this->filename));
    }
    protected function get_current_version(): string
    {
        return Codecept::VERSION;
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $output->writeln(sprintf('<info>%s</info> version <comment>%s</comment>', self::NAME, $this->get_current_version()));
        $updater = new Updater(null, false);
        $updater->get_strategy()->set_phar_url(self::PHAR_URL . 'codecept.phar');
        $updater->get_strategy()->set_version_url(self::PHAR_URL . 'codecept.version');
        try {
            if ($updater->has_update()) {
                $output->writeln("\n<info>Updating...</info>");
                $updater->update();
                $output->writeln("\n<comment>{$this->filename}</comment> has been updated.\n");
            } else {
                $output->writeln('You are already using the latest version.');
            }
        } catch (Exception $exception) {
            $output->writeln("<error>\n{$exception->get_message()}\n</error>");
            return Command::FAILURE;
        }
        return Command::SUCCESS;
    }
}
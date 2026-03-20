<?php

declare (strict_types=1);
namespace Codeception\Lib\Console;

use Exception;
use Symfony\Component\Console\Formatter\Output_Formatter;
use Symfony\Component\Console\Formatter\Output_Formatter_Style;
use Symfony\Component\Console\Helper\Formatter_Helper as SymfonyFormatterHelper;
use Symfony\Component\Console\Output\Console_Output;
class Output extends Console_Output
{
    /**
     * @var array<string, int|bool>
     */
    protected array $config = ['colors' => true, 'verbosity' => self::VERBOSITY_NORMAL, 'interactive' => true];
    public Symfony_Formatter_Helper $format_helper;
    public bool $wait_for_debug_output = true;
    protected bool $is_interactive = false;
    public function __construct(array $config)
    {
        $this->config = array_merge($this->config, $config);
        $this->is_interactive = $this->config['interactive'] && isset($_SERVER['TERM']) && PHP_SAPI === 'cli' && $_SERVER['TERM'] != 'linux';
        $formatter = new Output_Formatter($this->config['colors']);
        $this->configure_styles($formatter);
        $this->format_helper = new Symfony_Formatter_Helper();
        parent::__construct($this->config['verbosity'], $this->config['colors'], $formatter);
    }
    protected function configure_styles(Output_Formatter $formatter): void
    {
        $formatter->set_style('default', new Output_Formatter_Style());
        $formatter->set_style('bold', new Output_Formatter_Style(null, null, ['bold']));
        $formatter->set_style('focus', new Output_Formatter_Style('magenta', null, ['bold']));
        $formatter->set_style('ok', new Output_Formatter_Style('green', null, ['bold']));
        $formatter->set_style('error', new Output_Formatter_Style('white', 'red', ['bold']));
        $formatter->set_style('fail', new Output_Formatter_Style('red', null, ['bold']));
        $formatter->set_style('pending', new Output_Formatter_Style('yellow', null, ['bold']));
        $formatter->set_style('debug', new Output_Formatter_Style('cyan'));
        $formatter->set_style('comment', new Output_Formatter_Style('yellow'));
        $formatter->set_style('info', new Output_Formatter_Style('green'));
    }
    protected function clean(string $message): string
    {
        return str_replace('\/', '/', $message);
    }
    public function is_interactive(): bool
    {
        return $this->is_interactive;
    }
    public function debug(mixed $message): void
    {
        if ($this->wait_for_debug_output) {
            $this->writeln('');
            $this->wait_for_debug_output = false;
        }
        if (!is_string($message)) {
            dump($message);
            return;
        }
        $message = $this->clean($message);
        $message = Output_Formatter::escape($message);
        $this->writeln("<debug>  {$message}</debug>");
    }
    public function message($message): Message
    {
        $message = sprintf(...func_get_args());
        return new Message($message, $this);
    }
    public function exception(Exception $exception): void
    {
        $class = $exception::class;
        $this->writeln('');
        $this->writeln(sprintf('(![ %s ]!)', $class));
        $this->writeln($exception->get_message());
        $this->writeln('');
    }
    public function notification(string $message): void
    {
        $this->writeln("<comment>{$message}</comment>");
    }
}
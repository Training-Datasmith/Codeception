<?php

declare (strict_types=1);
namespace Codeception\Lib\Console;

class Repl_History
{
    protected string $output_file;
    protected array $stashed_commands = [];
    protected static ?self $instance = null;
    private function __construct()
    {
        $this->output_file = codecept_output_dir('stashed-commands');
        if (file_exists($this->output_file)) {
            unlink($this->output_file);
        }
    }
    public static function get_instance(): Repl_History
    {
        if (!static::$instance instanceof Repl_History) {
            static::$instance = new self();
        }
        return static::$instance;
    }
    public function add($command): void
    {
        $this->stashed_commands[] = $command;
    }
    public function get_all(): array
    {
        return $this->stashed_commands;
    }
    public function clear(): void
    {
        $this->stashed_commands = [];
    }
    public function save(): void
    {
        if ($this->stashed_commands === []) {
            return;
        }
        file_put_contents($this->output_file, implode("\n", $this->stashed_commands) . "\n", FILE_APPEND);
        codecept_debug("Stashed commands have been saved to {$this->output_file}");
        $this->clear();
    }
}
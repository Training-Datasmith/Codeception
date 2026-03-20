<?php

declare (strict_types=1);
namespace Codeception\Lib;

use Psy\Configuration;
use Psy\Shell;
class Pause_Shell
{
    public const LOG_FILE = '.pause.log';
    private readonly Configuration $psy_conf;
    public function __construct()
    {
        $relative_log_file_path = codecept_relative_path(codecept_output_dir(self::LOG_FILE));
        $this->psy_conf = new Configuration(['prompt' => '>> ', 'startupMessage' => "<warning>Execution PAUSED</warning> All commands will be saved to {$relative_log_file_path}", 'historyFile' => codecept_output_dir(self::LOG_FILE), 'historySize' => 1000]);
    }
    public function add_message(string $message): self
    {
        $this->psy_conf->set_startup_message($this->psy_conf->get_startup_message() . "\n" . $message);
        return $this;
    }
    public function get_shell(): Shell
    {
        return new Shell($this->psy_conf);
    }
}
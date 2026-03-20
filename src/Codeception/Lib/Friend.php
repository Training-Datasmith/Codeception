<?php

declare (strict_types=1);
namespace Codeception\Lib;

use Codeception\Actor;
use Codeception\Exception\Test_Runtime_Exception;
use Codeception\Lib\Interfaces\Multi_Session;
class Friend
{
    protected array $data = [];
    protected array $multi_session_modules = [];
    public function __construct(protected string $name, protected Actor $actor, array $modules = [])
    {
        $this->multi_session_modules = array_filter($modules, fn($m): bool => $m instanceof Multi_Session);
        if ($this->multi_session_modules === []) {
            throw new Test_Runtime_Exception("No multisession modules used. Can't instantiate friend");
        }
    }
    public function does(callable $closure)
    {
        $current_user_data = [];
        foreach ($this->multi_session_modules as $module) {
            $name = $module->_get_name();
            $current_user_data[$name] = $module->_backup_session();
            if (empty($this->data[$name])) {
                $module->_initialize_session();
                $this->data[$name] = $module->_backup_session();
                continue;
            }
            $module->_load_session($this->data[$name]);
        }
        $this->actor->comment(strtoupper("{$this->name} does ---"));
        $result = $closure($this->actor);
        $this->actor->comment(strtoupper("--- {$this->name} finished"));
        foreach ($this->multi_session_modules as $module) {
            $name = $module->_get_name();
            $this->data[$name] = $module->_backup_session();
            $module->_load_session($current_user_data[$name]);
        }
        return $result;
    }
    public function is_going_to(string $argumentation): void
    {
        $this->actor->am_going_to($argumentation);
    }
    public function expects(string $prediction): void
    {
        $this->actor->expect($prediction);
    }
    public function expects_to(string $prediction): void
    {
        $this->actor->expect_to($prediction);
    }
    public function leave(): void
    {
        foreach ($this->multi_session_modules as $module) {
            $name = $module->_get_name();
            if (isset($this->data[$name])) {
                $module->_close_session($this->data[$name]);
            }
        }
    }
}
<?php

declare (strict_types=1);
namespace Codeception\Lib\Actor\Shared;

use Codeception\Command\Console;
use Codeception\Lib\Pause_Shell;
use Codeception\Util\Debug;
trait Pause
{
    public function pause(array $vars = []): void
    {
        if (!Debug::is_enabled()) {
            return;
        }
        $psy = (new Pause_Shell())->add_message('$I-> to launch commands')->add_message('$this-> to access current test')->add_message('exit to exit')->get_shell();
        $vars['I'] = $this;
        $psy->set_scope_variables($vars);
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 2);
        if (!$backtrace[1]['object'] instanceof Console) {
            // set the scope of test class
            $psy->set_bound_object($backtrace[1]['object']);
        }
        $psy->run();
    }
}
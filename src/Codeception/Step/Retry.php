<?php

declare (strict_types=1);
namespace Codeception\Step;

use function codecept_debug;
use Codeception\Lib\Module_Container;
use Codeception\Util\Template;
use Exception;
use function ucfirst;
use function usleep;
class Retry extends Assertion implements Generated_Step
{
    protected static string $method_template = <<<EOF
    
        /**
         * [!] Method is generated.
         *
         * {{doc}}
         *
         * Retry number and interval set by \$I->retry();
         *
         * @see \\{{module}}::{{method}}()
         */
        public function {{action}}({{params}}) {
            \$retryNum      = \$this->retryNum ?? 1;
            \$retryInterval = \$this->retryInterval ?? 200;
            return \$this->getScenario()->runStep(new \\Codeception\\Step\\Retry('{{method}}', func_get_args(), \$retryNum, \$retryInterval));
        }
    EOF;
    public function __construct($action, array $arguments, private readonly int $retry_num, private readonly int $retry_interval)
    {
        $this->action = $action;
        $this->arguments = $arguments;
    }
    public function run(?Module_Container $container = null)
    {
        $attempts = 0;
        $interval = $this->retry_interval;
        while (true) {
            try {
                $this->is_try = $attempts < $this->retry_num;
                return parent::run($container);
            } catch (Exception $e) {
                ++$attempts;
                if (!$this->is_try) {
                    throw $e;
                }
                codecept_debug("Retrying #{$attempts} in {$interval}ms");
                usleep($interval * 1000);
                $interval *= 2;
            }
        }
    }
    public static function get_template(Template $template): ?Template
    {
        $action = (string) $template->get_var('action');
        if (str_starts_with($action, 'have') || str_starts_with($action, 'am') || str_starts_with($action, 'wait')) {
            return null;
        }
        $doc = "* Executes {$action} and retries on failure.";
        return (new Template(self::$method_template))->place('method', $template->get_var('method'))->place('module', $template->get_var('module'))->place('params', $template->get_var('params'))->place('doc', $doc)->place('action', 'retry' . ucfirst($action));
    }
}
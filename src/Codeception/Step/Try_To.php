<?php

declare (strict_types=1);
namespace Codeception\Step;

use function codecept_debug;
use Codeception\Lib\Module_Container;
use Codeception\Util\Template;
use Exception;
use function ucfirst;
class Try_To extends Assertion implements Generated_Step
{
    public function run(?Module_Container $container = null): bool
    {
        $this->is_try = true;
        try {
            parent::run($container);
        } catch (Exception $e) {
            codecept_debug("Failed to perform: {$e->get_message()}, skipping...");
            return false;
        }
        return true;
    }
    public static function get_template(Template $template): ?Template
    {
        $action = (string) $template->get_var('action');
        if (str_starts_with($action, 'have') || str_starts_with($action, 'am') || str_starts_with($action, 'wait') || str_starts_with($action, 'grab')) {
            return null;
        }
        $conditional_doc = "* [!] Test won't be stopped on fail. Error won't be logged \n     " . $template->get_var('doc');
        return $template->place('doc', $conditional_doc)->place('action', 'tryTo' . ucfirst($action))->place('return', 'return ')->place('return_type', ': bool')->place('step', 'TryTo');
    }
}
<?php

declare (strict_types=1);
namespace Codeception\Step;

use Codeception\Exception\Conditional_Assertion_Failed;
use Codeception\Lib\Module_Container;
use Codeception\Util\Template;
use Php_Unit\Framework\Assertion_Failed_Error;
use function preg_replace;
use function str_replace;
use function ucfirst;
class Conditional_Assertion extends Assertion implements Generated_Step
{
    public function run(?Module_Container $container = null): void
    {
        try {
            parent::run($container);
        } catch (Assertion_Failed_Error $e) {
            throw new Conditional_Assertion_Failed($e->get_message(), $e->get_code(), $e);
        }
    }
    public function get_action(): string
    {
        $action = 'can' . ucfirst($this->action);
        return (string) preg_replace('#^canDont#', 'cant', $action);
    }
    public function get_humanized_action(): string
    {
        return $this->humanize($this->action . ' ' . $this->get_humanized_arguments());
    }
    public static function get_template(Template $template): ?Template
    {
        $action = (string) $template->get_var('action');
        if (!str_starts_with($action, 'see') && !str_starts_with($action, 'dontSee')) {
            return null;
        }
        $conditional_doc = "* [!] Conditional Assertion: Test won't be stopped on fail\n     " . $template->get_var('doc');
        $prefix = 'can';
        if (str_starts_with($action, 'dontSee')) {
            $prefix = 'cant';
            $action = str_replace('dont', '', $action);
        }
        return $template->place('doc', $conditional_doc)->place('action', $prefix . ucfirst($action))->place('step', 'ConditionalAssertion');
    }
    public function match(string $name): bool
    {
        return str_starts_with($name, 'see') || str_starts_with($name, 'dontSee');
    }
}
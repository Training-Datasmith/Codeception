<?php

declare (strict_types=1);
namespace Codeception\Lib\Generator;

use Codeception\Configuration;
use Codeception\Lib\Di;
use Codeception\Lib\Friend;
use Codeception\Lib\Generator\Shared\Classname;
use Codeception\Lib\Module_Container;
use Codeception\Util\Reflection_Helper;
use Codeception\Util\Template;
use ReflectionClass;
use ReflectionMethod;
class Actor
{
    use Classname;
    public Di $di;
    public Module_Container $module_container;
    protected string $template = <<<EOF
    <?php
    
    declare(strict_types=1);
    {{hasNamespace}}
    
    /**
     * Inherited Methods
    {{inheritedMethods}}
     *
     * @SuppressWarnings(PHPMD)
    */
    class {{actor}} extends \\Codeception\\Actor
    {
        use _generated\\{{actor}}Actions;
    
        /**
         * Define custom actions here
         */
    }
    
    EOF;
    protected string $inherited_method_template = ' * @method {{return}} {{method}}({{params}})';
    protected array $modules = [];
    protected array $actions = [];
    public function __construct(protected array $settings)
    {
        $this->di = new Di();
        $this->module_container = new Module_Container($this->di, $settings);
        $modules = Configuration::modules($this->settings);
        foreach ($modules as $module_name) {
            $this->module_container->create($module_name);
        }
        $this->modules = $this->module_container->all();
        $this->actions = $this->module_container->get_actions();
    }
    public function produce(): string
    {
        $namespace = trim($this->support_namespace(), '\\');
        return (new Template($this->template))->place('hasNamespace', $namespace !== '' ? "\nnamespace {$namespace};" : '')->place('actor', $this->settings['actor'])->place('inheritedMethods', $this->prepend_abstract_actor_doc_blocks())->produce();
    }
    protected function prepend_abstract_actor_doc_blocks(): string
    {
        $inherited = [];
        $class = new ReflectionClass(\Codeception\Actor::class);
        $methods = $class->get_methods(ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            if ($method->name == '__call') {
                continue;
            }
            // skipping magic
            if ($method->name == '__construct') {
                continue;
            }
            // skipping magic
            $return_type = 'void';
            if ($method->name == 'haveFriend') {
                $return_type = Friend::class;
            }
            $params = $this->get_params_string($method);
            $inherited[] = (new Template($this->inherited_method_template))->place('method', $method->name)->place('params', $params)->place('return', $return_type)->produce();
        }
        return implode("\n", $inherited);
    }
    protected function get_params_string(ReflectionMethod $ref_method): string
    {
        $params = [];
        foreach ($ref_method->get_parameters() as $param) {
            if ($param->is_optional()) {
                $params[] = '$' . $param->name . ' = ' . Reflection_Helper::get_default_value($param);
            } else {
                $params[] = '$' . $param->name;
            }
        }
        return implode(', ', $params);
    }
    public function get_actor_name()
    {
        return $this->settings['actor'];
    }
    /**
     * @return string[]
     */
    public function get_modules(): array
    {
        return array_keys($this->modules);
    }
}
<?php

declare (strict_types=1);
namespace Codeception\Lib\Generator;

use Codeception\Exception\Configuration_Exception;
use Codeception\Lib\Generator\Shared\Classname;
use Codeception\Util\Shared\Namespaces;
use Codeception\Util\Template;
class Step_Object
{
    use Namespaces;
    use Classname;
    protected string $template = <<<EOF
    <?php
    
    declare(strict_types=1);
    
    namespace {{namespace}};
    
    class {{name}} extends {{actorClass}}
    {
    {{actions}}
    }
    
    EOF;
    protected string $action_template = <<<EOF
    
        public function {{action}}()
        {
            \$I = \$this;
        }
    
    EOF;
    protected string $name;
    protected string $actions = '';
    public string $namespace;
    public function __construct(protected array $settings, string $name)
    {
        $this->name = $this->get_short_class_name($name);
        $this->namespace = $this->get_namespace_string($this->support_namespace() . 'Step\\' . $name);
    }
    public function produce(): string
    {
        $actor = $this->settings['actor'];
        if (!$actor) {
            throw new Configuration_Exception("Steps can't be created for suite without an actor");
        }
        $extended = '\\' . ltrim($this->support_namespace() . $actor, '\\');
        return (new Template($this->template))->place('namespace', $this->namespace)->place('name', $this->name)->place('actorClass', $extended)->place('actions', $this->actions)->produce();
    }
    public function create_action($action): void
    {
        $this->actions .= (new Template($this->action_template))->place('action', $action)->produce();
    }
}
<?php

declare (strict_types=1);
namespace Codeception\Lib\Generator;

use Codeception\Configuration;
use Codeception\Lib\Generator\Shared\Classname;
use Codeception\Util\Shared\Namespaces;
use Codeception\Util\Template;
class Test
{
    use Namespaces;
    use Classname;
    protected string $template = <<<EOF
    <?php
    
    {{namespace}}
    
    class {{name}}Test extends \\Codeception\\Test\\Unit
    {
    {{tester}}
        protected function _before()
        {
        }
    
        // tests
        public function testSomeFeature()
        {
    
        }
    }
    
    EOF;
    protected string $tester_template = <<<EOF
    
        protected {{actorClass}} \${{actor}};
    
    EOF;
    protected string $name;
    public function __construct(protected array $settings, string $name)
    {
        $this->name = $this->remove_suffix($name, 'Test');
    }
    public function produce(): string
    {
        $actor = $this->settings['actor'];
        $namespace_path = $this->settings['namespace'] . '\\' . ucfirst((string) $this->settings['suite']) . '\\' . $this->name;
        $ns = $this->get_namespace_header($namespace_path);
        if ($ns) {
            $ns .= "\nuse " . $this->support_namespace() . $actor . ';';
        }
        $tester = '';
        if ($actor) {
            $tester = (new Template($this->tester_template))->place('actorClass', $actor)->place('actor', lcfirst((string) Configuration::config()['actor_suffix']))->produce();
        }
        return (new Template($this->template))->place('namespace', $ns)->place('name', $this->get_short_class_name($this->name))->place('tester', $tester)->produce();
    }
}
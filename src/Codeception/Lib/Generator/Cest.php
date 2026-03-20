<?php

declare (strict_types=1);
namespace Codeception\Lib\Generator;

use Codeception\Exception\Configuration_Exception;
use Codeception\Lib\Generator\Shared\Classname;
use Codeception\Util\Shared\Namespaces;
use Codeception\Util\Template;
class Cest
{
    use Classname;
    use Namespaces;
    protected string $template = <<<EOF
    <?php
    
    declare(strict_types=1);
    {{namespace}}
    
    final class {{name}}Cest
    {
        public function _before({{actor}} \$I): void
        {
            // Code here will be executed before each test function.
        }
    
        // All `public` methods will be executed as tests.
        public function tryToTest({{actor}} \$I): void
        {
            // Write your test content here.
        }
    }
    
    EOF;
    protected ?string $name;
    public function __construct(string $class_name, protected array $settings)
    {
        $this->name = $this->remove_suffix($class_name, 'Cest');
    }
    public function produce(): string
    {
        $actor = $this->settings['actor'];
        if (!$actor) {
            throw new Configuration_Exception("Cest can't be created for suite without an actor. Add `actor: SomeTester` to suite config");
        }
        $namespace_header = $this->get_namespace_header($this->settings['namespace'] . '\\' . ucfirst((string) $this->settings['suite']) . '\\' . $this->name);
        if ($namespace_header) {
            $namespace_header .= "\nuse " . $this->support_namespace() . $actor . ';';
        }
        return (new Template($this->template))->place('name', $this->get_short_class_name($this->name))->place('namespace', $namespace_header)->place('actor', $actor)->produce();
    }
}
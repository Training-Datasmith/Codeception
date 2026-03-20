<?php

declare (strict_types=1);
namespace Codeception\Lib\Generator;

use Behat\Gherkin\Node\Step_Node;
use Codeception\Test\Loader\Gherkin;
use Codeception\Util\Template;
use Symfony\Component\Finder\Finder;
class Gherkin_Snippets
{
    protected string $template = <<<EOF
        #[\\Codeception\\Attribute\\{{type}}('{{text}}')]
        public function {{methodName}}({{params}})
        {
            throw new \\PHPUnit\\Framework\\IncompleteTestError('Step `{{text}}` is not defined');
        }
    
    EOF;
    /**
     * @var string[]
     */
    protected array $snippets = [];
    /**
     * @var string[]
     */
    protected array $processed = [];
    /**
     * @var string[]
     */
    protected array $features = [];
    public function __construct(array $settings, ?string $test = null)
    {
        $loader = new Gherkin($settings);
        $path = $settings['path'];
        $pattern = $loader->get_pattern();
        if ($test) {
            $path = "{$path}/{$test}";
            if (preg_match($pattern, $test)) {
                $path = dirname($path);
                $pattern = basename($test);
            }
        }
        $finder = Finder::create()->files()->sort_by_name()->in($path)->follow_links()->name($pattern);
        foreach ($finder as $file) {
            $loader->load_tests($file->get_pathname());
        }
        $available_steps = $loader->get_steps();
        $all_steps = [];
        foreach ($available_steps as $step_group) {
            $all_steps = array_merge($all_steps, $step_group);
        }
        foreach ($loader->get_tests() as $test) {
            $steps = $test->get_scenario_node()->get_steps();
            if ($test->get_feature_node()->has_background()) {
                $steps = array_merge($steps, $test->get_feature_node()->get_background()->get_steps());
            }
            foreach ($steps as $step) {
                $matched = false;
                $text = $step->get_text();
                if (self::step_has_py_string_argument($step)) {
                    // pretend it is inline argument
                    $text .= ' ""';
                }
                foreach (array_keys($all_steps) as $pattern) {
                    if (preg_match($pattern, (string) $text)) {
                        $matched = true;
                        break;
                    }
                }
                if (!$matched) {
                    $this->add_snippet($step);
                    $file = str_ireplace($settings['path'], '', $test->get_feature_node()->get_file());
                    if (!in_array($file, $this->features)) {
                        $this->features[] = $file;
                    }
                }
            }
        }
    }
    public function add_snippet(Step_Node $step): void
    {
        $args = [];
        $pattern = $step->get_text();
        // match numbers (not in quotes)
        $pattern = preg_replace_callback('#([\d.])(?=([^"]*"[^"]*")*[^"]*$)#', function () use (&$args): string {
            $args[] = '$num' . (count($args) + 1);
            return ':num' . count($args);
        }, $pattern);
        // match quoted strings
        $pattern = preg_replace_callback('#"(.*?)"#', function () use (&$args): string {
            $args[] = '$arg' . (count($args) + 1);
            return ':arg' . count($args);
        }, $pattern);
        // add multiline argument if present
        if (self::step_has_py_string_argument($step)) {
            $args[] = '$arg' . (count($args) + 1);
            $pattern .= ' :arg' . count($args);
        }
        if (in_array($pattern, $this->processed)) {
            return;
        }
        $method_name = preg_replace('#(\s+?|\'|\"|\W)#', '', ucwords(preg_replace('#"(.*?)"|\d+#', '', $step->get_text())));
        $method_name = empty($method_name) ? 'step_' . substr(sha1($pattern), 0, 9) : lcfirst($method_name);
        $this->snippets[] = (new Template($this->template))->place('type', $step->get_keyword_type())->place('text', str_replace(['\\', "'"], ['\\\\', "\\'"], $pattern))->place('methodName', $method_name)->place('params', implode(', ', $args))->produce();
        $this->processed[] = $pattern;
    }
    /**
     * @return string[]
     */
    public function get_snippets(): array
    {
        return $this->snippets;
    }
    /**
     * @return string[]
     */
    public function get_features(): array
    {
        return $this->features;
    }
    public static function step_has_py_string_argument(Step_Node $step): bool
    {
        if ($step->has_arguments()) {
            $step_args = $step->get_arguments();
            return end($step_args)->get_node_type() === 'PyString';
        }
        return false;
    }
}
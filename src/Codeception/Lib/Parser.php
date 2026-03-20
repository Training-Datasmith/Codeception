<?php

declare (strict_types=1);
namespace Codeception\Lib;

use Codeception\Exception\Test_Parse_Exception;
use Codeception\Scenario;
use Codeception\Step\Action;
use Codeception\Step\Comment;
use Codeception\Test\Metadata;
use Exception;
use ParseError;
class Parser
{
    protected string $code;
    public function __construct(protected Scenario $scenario, protected Metadata $metadata)
    {
    }
    public function prepare_to_run(string $code): void
    {
        $this->parse_feature($code);
        $this->parse_scenario_options($code);
    }
    public function parse_feature(string $code): void
    {
        $code = $this->strip_comments($code);
        if (preg_match("#\\\$I->(wantTo|wantToTest)\\(\\s*?['\"](.*?)['\"]\\s*?\\);#", $code, $matches)) {
            $feature = $matches[1] === 'wantToTest' ? "test {$matches[2]}" : $matches[2];
            $this->scenario->set_feature($feature);
        }
    }
    public function parse_scenario_options(string $code): void
    {
        $this->metadata->set_params_from_annotations($this->match_comments($code));
    }
    public function parse_steps(string $code): void
    {
        $friends = [];
        $lines = explode("\n", $code);
        $is_friend = false;
        foreach ($lines as $line) {
            if (preg_match('#\$I->haveFriend\((.*?)\);#', $line, $matches)) {
                // Friends
                $friends[] = trim($matches[1], '\'"');
            }
            if (preg_match('#\$(.*?)->does\(#', $line, $matches)) {
                // Friends section start
                $friend = $matches[1];
                if (!in_array($friend, $friends)) {
                    continue;
                }
                $is_friend = true;
                $this->add_comment_step("\n----- {$friend} does -----");
                continue;
            }
            if (preg_match('#\$I->(.*)\((.*?)\);#', $line, $matches)) {
                // Actions
                $this->add_step($matches);
            }
            if ($is_friend && str_contains($line, '}')) {
                // Friends section ends
                $this->add_comment_step("-------- back to me\n");
                $is_friend = false;
            }
        }
    }
    /** @param string[] $matches */
    protected function add_step(array $matches): void
    {
        [$m, $action, $params] = $matches;
        if (!in_array($action, ['wantTo', 'wantToTest'])) {
            $this->scenario->add_step(new Action($action, explode(',', $params)));
        }
    }
    protected function add_comment_step(string $comment): void
    {
        $this->scenario->add_step(new Comment($comment, []));
    }
    public static function load(string $file): void
    {
        try {
            self::include_file($file);
        } catch (ParseError $e) {
            throw new Test_Parse_Exception($file, $e->get_message(), $e->get_line());
        } catch (Exception) {
            // file is valid otherwise
        }
    }
    /**
     * @return string[]
     */
    public static function get_classes_from_file(string $file): array
    {
        $source_code_tokens = token_get_all(file_get_contents($file), TOKEN_PARSE);
        $classes = [];
        $namespace = '';
        foreach ($source_code_tokens as $i => $token) {
            if ($token[0] === T_NAMESPACE) {
                $namespace = self::extract_namespace($source_code_tokens, $i);
            }
            if ($token[0] === T_CLASS) {
                $class = self::extract_class($source_code_tokens, $i);
                if ($class) {
                    $classes[] = $namespace . $class;
                }
            }
        }
        gc_mem_caches();
        return $classes;
    }
    private static function extract_namespace(array $tokens, int $index): string
    {
        $namespace = '';
        $counter = count($tokens);
        for ($j = $index + 1; $j < $counter; ++$j) {
            if ($tokens[$j] === '{' || $tokens[$j] === ';') {
                break;
            }
            if ($tokens[$j][0] === T_STRING || $tokens[$j][0] === T_NAME_QUALIFIED) {
                $namespace .= $tokens[$j][1] . '\\';
            }
        }
        return $namespace;
    }
    private static function extract_class(array $tokens, int $index): ?string
    {
        // class at the beginning of file
        if (!isset($tokens[$index - 2])) {
            return $tokens[$index + 2][1] ?? null;
        }
        // new class
        if (isset($tokens[$index - 2]) && $tokens[$index - 2][0] === T_NEW) {
            return null;
        }
        // :: class
        if (isset($tokens[$index - 1]) && $tokens[$index - 1][0] === T_WHITESPACE && isset($tokens[$index - 2]) && $tokens[$index - 2][0] === T_DOUBLE_COLON) {
            return null;
        }
        // ::class
        if (isset($tokens[$index - 1]) && $tokens[$index - 1][0] === T_DOUBLE_COLON) {
            return null;
        }
        // class{
        if (isset($tokens[$index + 1]) && $tokens[$index + 1] === '{') {
            return null;
        }
        // class {
        if (isset($tokens[$index + 2]) && $tokens[$index + 1][0] === T_WHITESPACE && $tokens[$index + 2] === '{') {
            return null;
        }
        return $tokens[$index + 2][1] ?? null;
    }
    /*
     * Include in different scope to prevent included file from affecting $file variable
     */
    private static function include_file(string $file): void
    {
        include_once $file;
    }
    protected function strip_comments(string $code): string
    {
        return preg_replace(['#//.*?$#m', '#/*\*.*?\*/#ms'], '', $code);
        // inline & block comments
    }
    protected function match_comments(string $code): string
    {
        preg_match_all('#//(.*?)$#m', $code, $line_matches);
        preg_match('#/\*(.*?)\*/#ms', $code, $block_match);
        $line_comments = implode("\n", $line_matches[1] ?? []);
        $block_comments = $block_match[1] ?? '';
        return $line_comments . "\n" . $block_comments . "\n";
    }
}
<?php

declare (strict_types=1);
namespace Codeception\Coverage;

use function array_pop;
use Codeception\Configuration;
use Codeception\Exception\Configuration_Exception;
use Codeception\Exception\Module_Exception;
use function explode;
use function implode;
use function is_array;
use function iterator_to_array;
use Php_Unit\Runner\Version as PHPUnitVersion;
use Sebastian_Bergmann\Code_Coverage\Code_Coverage;
use Sebastian_Bergmann\Code_Coverage\Filter as PhpUnitFilter;
use function str_replace;
use Symfony\Component\Finder\Exception\Directory_Not_Found_Exception;
use Symfony\Component\Finder\Finder;
class Filter
{
    protected static ?self $codeception_filter = null;
    protected ?Php_Unit_Filter $php_unit_filter = null;
    public function __construct(protected ?Code_Coverage $php_code_coverage)
    {
        $this->php_unit_filter = $this->php_code_coverage->filter();
    }
    public static function setup(Code_Coverage $php_coverage): self
    {
        self::$codeception_filter = new self($php_coverage);
        return self::$codeception_filter;
    }
    /**
     * @throws ConfigurationException
     */
    public function white_list(array $config): self
    {
        $filter = $this->php_unit_filter;
        if (!isset($config['coverage'])) {
            return $this;
        }
        $coverage = $config['coverage'];
        if (!isset($coverage['whitelist'])) {
            $coverage['whitelist'] = [];
            if (isset($coverage['include'])) {
                $coverage['whitelist']['include'] = $coverage['include'];
            }
            if (isset($coverage['exclude'])) {
                $coverage['whitelist']['exclude'] = $coverage['exclude'];
            }
        }
        if (Php_Unit_Version::series() >= 11) {
            return $this->new_white_list($coverage['whitelist']);
        }
        foreach (['include', 'exclude'] as $type) {
            if (!isset($coverage['whitelist'][$type])) {
                continue;
            }
            if (!is_array($coverage['whitelist'][$type])) {
                throw new Configuration_Exception("Error parsing yaml. Config `whitelist: {$type}:` should be an array");
            }
            foreach ($coverage['whitelist'][$type] as $file_or_dir) {
                try {
                    $finder = str_contains((string) $file_or_dir, '*') ? $this->match_wildcard_pattern($file_or_dir) : [Configuration::project_dir() . DIRECTORY_SEPARATOR . $file_or_dir];
                    foreach ($finder as $file) {
                        $file = (string) $file;
                        $type === 'include' ? $filter->include_file($file) : $filter->exclude_file($file);
                    }
                } catch (Directory_Not_Found_Exception) {
                    continue;
                }
            }
        }
        return $this;
    }
    private function new_white_list(array $whitelist): self
    {
        $include = $whitelist['include'] ?? [];
        $exclude = $whitelist['exclude'] ?? [];
        if (!is_array($include)) {
            throw new Configuration_Exception('Error parsing yaml. Config `whitelist: include:` should be an array');
        }
        if (!is_array($exclude)) {
            throw new Configuration_Exception('Error parsing yaml. Config `whitelist: exclude:` should be an array');
        }
        if ($exclude === [] && $include === []) {
            return $this;
        }
        if ($include === []) {
            $include = [Configuration::project_dir() . DIRECTORY_SEPARATOR . '*'];
        }
        $all_included_files = $this->match_files($include);
        $all_excluded_files = $this->match_files($exclude);
        $covered_files = array_diff($all_included_files, $all_excluded_files);
        foreach ($covered_files as $covered_file) {
            $this->php_unit_filter->include_file((string) $covered_file);
        }
        return $this;
    }
    private function match_files(array $files): array
    {
        $matched_files = [];
        foreach ($files as $file_or_dir) {
            try {
                $finder = str_contains((string) $file_or_dir, '*') ? $this->match_wildcard_pattern($file_or_dir) : $this->match_file_or_directory($file_or_dir);
                $matched_files += iterator_to_array($finder->getIterator());
            } catch (Directory_Not_Found_Exception) {
                continue;
            }
        }
        return $matched_files;
    }
    /**
     * @throws ModuleException
     */
    public function black_list(array $config): self
    {
        if (isset($config['coverage']['blacklist'])) {
            throw new Module_Exception($this, 'The blacklist functionality has been removed from PHPUnit 5,' . ' please remove blacklist section from configuration.');
        }
        return $this;
    }
    private function match_file_or_directory(string $file_or_dir): Finder
    {
        $full_path = Configuration::project_dir() . $file_or_dir;
        $finder = Finder::create();
        if (is_dir($full_path)) {
            $finder->in($full_path);
            $finder->name('*.php');
        } else {
            $finder->in(dirname($full_path));
            $finder->name(basename($full_path));
        }
        $finder->ignore_vcs(true)->files();
        return $finder;
    }
    protected function match_wildcard_pattern(string $pattern): Finder
    {
        $finder = Finder::create();
        $file_or_dir = str_replace('\\', '/', $pattern);
        $parts = explode('/', $file_or_dir);
        $file = array_pop($parts);
        if ($file === '*') {
            $file = '*.php';
        }
        $finder->name($file);
        if ($parts !== []) {
            $last_path = array_pop($parts);
            $path = implode('/', $last_path === '*' ? $parts : [...$parts, $last_path]);
            $finder->in(Configuration::project_dir() . $path);
        }
        $finder->ignore_vcs(true)->files();
        return $finder;
    }
    public function get_filter(): Php_Unit_Filter
    {
        return $this->php_unit_filter;
    }
}
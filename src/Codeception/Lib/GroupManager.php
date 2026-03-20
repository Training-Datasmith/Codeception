<?php

declare (strict_types=1);
namespace Codeception\Lib;

use Codeception\Configuration;
use Codeception\Exception\Configuration_Exception;
use Codeception\Test\Gherkin;
use Codeception\Test\Test;
use Codeception\Util\Path_Resolver;
use function realpath;
use Symfony\Component\Finder\Finder;
/**
 * Loads information for groups from external sources (config, filesystem)
 */
class Group_Manager
{
    protected array $tests_in_groups = [];
    protected string $root_dir;
    /** @param string[] $configuredGroups */
    public function __construct(protected array $configured_groups)
    {
        $this->root_dir = Configuration::base_dir();
        $this->load_groups_by_pattern();
        $this->load_configured_group_settings();
    }
    /**
     * proceeds group names with asterisk:
     *
     * ```
     * "tests/_log/g_*" => [
     *      "tests/_log/group_1",
     *      "tests/_log/group_2",
     *      "tests/_log/group_3",
     * ]
     * ```
     */
    protected function load_groups_by_pattern(): void
    {
        foreach ($this->configured_groups as $group => $pattern) {
            if (!str_contains((string) $group, '*')) {
                continue;
            }
            $path = Path_Resolver::is_path_absolute($pattern) ? dirname($pattern) : $this->root_dir . dirname($pattern);
            $files = Finder::create()->files()->name(basename($pattern))->sort_by_name()->in($path);
            foreach ($files as $file) {
                $prefix = str_replace('*', '', $group);
                $path_prefix = str_replace('*', '', basename($pattern));
                $group_name = $prefix . str_replace($path_prefix, '', $file->get_relative_pathname());
                $this->configured_groups[$group_name] = dirname($pattern) . DIRECTORY_SEPARATOR . $file->get_relative_pathname();
            }
            unset($this->configured_groups[$group]);
        }
    }
    protected function load_configured_group_settings(): void
    {
        foreach ($this->configured_groups as $group => $tests) {
            $this->tests_in_groups[$group] = [];
            $tests_array = is_array($tests) ? $tests : $this->get_tests_from_file($tests);
            foreach ($tests_array as $test) {
                $file = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $test);
                $this->tests_in_groups[$group][] = $this->normalize_file_path($file, $group);
            }
        }
    }
    private function get_tests_from_file(string $tests): array
    {
        $path = codecept_is_path_absolute($tests) ? $tests : $this->root_dir . $tests;
        if (!is_file($path)) {
            return [];
        }
        $tests_array = [];
        $handle = fopen($path, 'r');
        if ($handle) {
            while (($test = fgets($handle, 4096)) !== false) {
                // if the current line is blank then we need to move to the next line
                // otherwise the current codeception directory becomes part of the group
                // which causes every single test to run
                if (trim($test) !== '') {
                    $tests_array[] = trim($test);
                }
            }
            fclose($handle);
        }
        return $tests_array;
    }
    private function normalize_file_path(string $file, string $group): string
    {
        $path_parts = explode(':', $file);
        $is_absolute = codecept_is_path_absolute($file);
        if ($is_absolute) {
            if ($file[0] === '/' && count($path_parts) > 1) {
                // Take segment before first :
                $this->check_if_file_exists($path_parts[0], $group);
                return sprintf('%s:%s', realpath($path_parts[0]), $path_parts[1]);
            }
            if (count($path_parts) > 2) {
                // On Windows take segment before second :
                $full_path = $path_parts[0] . ':' . $path_parts[1];
                $this->check_if_file_exists($full_path, $group);
                return sprintf('%s:%s', realpath($full_path), $path_parts[2]);
            }
            $this->check_if_file_exists($file, $group);
            return realpath($file);
        }
        if (!str_contains($file, ':')) {
            $dirty_path = $this->root_dir . $file;
            $this->check_if_file_exists($dirty_path, $group);
            return realpath($dirty_path);
        }
        $dirty_path = $this->root_dir . $path_parts[0];
        $this->check_if_file_exists($dirty_path, $group);
        return sprintf('%s:%s', realpath($dirty_path), $path_parts[1]);
    }
    private function check_if_file_exists(string $path, string $group): void
    {
        if (!file_exists($path)) {
            throw new Configuration_Exception('GroupManager: File or directory ' . $path . ' set in ' . $group . ' group does not exist');
        }
    }
    public function groups_for_test(Test $test): array
    {
        $filename = realpath($test->get_file_name());
        $test_name = $test->get_name();
        $groups = $test->get_metadata()->get_groups();
        foreach ($this->tests_in_groups as $group => $tests) {
            /** @var string[] $tests */
            foreach ($tests as $test_pattern) {
                if ($filename == $test_pattern || str_starts_with($filename . ':' . $test_name, $test_pattern)) {
                    $groups[] = $group;
                }
                if ($test instanceof Gherkin && mb_strtolower($filename . ':' . $test->get_metadata()->get_feature()) === mb_strtolower($test_pattern)) {
                    $groups[] = $group;
                }
            }
        }
        return array_unique($groups);
    }
}
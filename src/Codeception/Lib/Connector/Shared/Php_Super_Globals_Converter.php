<?php

declare (strict_types=1);
namespace Codeception\Lib\Connector\Shared;

/**
 * Converts BrowserKit\Request's request parameters and files into PHP-compatible structure
 *
 * @see https://bugs.php.net/bug.php?id=25589
 * @see https://bugs.php.net/bug.php?id=40000
 *
 * @package Codeception\Lib\Connector
 */
trait Php_Super_Globals_Converter
{
    /**
     * Rearrange files array to match PHP $_FILES structure.
     * Handles nested arrays within files, ensuring compatibility with PHP's $_FILES superglobal.
     */
    protected function remap_files(array $request_files): array
    {
        $normalized_files = $this->normalize_files_array($request_files);
        return $this->normalize_query_parameters($normalized_files);
    }
    /**
     * Normalize request parameters by replacing spaces and special characters.
     * Ensures compatibility with PHP's handling of query parameters.
     */
    protected function remap_request_parameters(array $parameters): array
    {
        return $this->normalize_query_parameters($parameters);
    }
    private function normalize_files_array(array $request_files): array
    {
        $normalized_files = [];
        foreach ($request_files as $field_name => $file_info) {
            if (!is_array($file_info)) {
                continue;
            }
            // Check if the current file info has nested arrays within its keys
            $contains_nested_arrays = count(array_filter($file_info, is_array(...)));
            if ($contains_nested_arrays || !isset($file_info['tmp_name'])) {
                $nested_files = $this->remap_files($file_info);
                // Convert from ['a' => ['tmp_name' => '/tmp/test.txt'] ]
                // to ['tmp_name' => ['a' => '/tmp/test.txt'] ]
                foreach ($nested_files as $nested_field_name => $nested_file_info) {
                    $nested_file_info = array_map(fn($value): array => [$nested_field_name => $value], $nested_file_info);
                    $normalized_files[$field_name] = array_replace_recursive($normalized_files[$field_name] ?? [], $nested_file_info);
                }
            } else {
                $normalized_files[$field_name] = $file_info;
            }
        }
        return $normalized_files;
    }
    /**
     * Normalize query parameters by replacing spaces and special characters.
     * Ensures compatibility with PHP's handling of query strings.
     */
    private function normalize_query_parameters(array $parameters): array
    {
        parse_str(http_build_query($parameters), $normalized_parameters);
        return $normalized_parameters;
    }
}
<?php

declare (strict_types=1);
namespace Codeception\Coverage;

use Codeception\Configuration;
use Sebastian_Bergmann\Code_Coverage\Code_Coverage;
use Sebastian_Bergmann\Code_Coverage\Driver\Selector;
use Sebastian_Bergmann\Code_Coverage\Filter as CodeCoverageFilter;
class Php_Code_Coverage_Factory
{
    private static ?Code_Coverage $instance = null;
    public static function build(): Code_Coverage
    {
        if (self::$instance instanceof Code_Coverage) {
            return self::$instance;
        }
        $coverage_config = Configuration::config()['coverage'];
        $path_coverage = $coverage_config['path_coverage'] ?? false;
        $filter = new Code_Coverage_Filter();
        $selector = new Selector();
        $driver = $path_coverage ? $selector->for_line_and_path_coverage($filter) : $selector->for_line_coverage($filter);
        return self::$instance = new Code_Coverage($driver, $filter);
    }
    public static function clear(): void
    {
        self::$instance = null;
    }
}
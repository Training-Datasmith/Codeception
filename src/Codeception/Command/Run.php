<?php

declare (strict_types=1);
namespace Codeception\Command;

use function array_flip;
use function array_intersect_key;
use function array_merge;
use Codeception\Codecept;
use Codeception\Configuration;
use Codeception\Exception\Configuration_Exception;
use Codeception\Exception\Parse_Exception;
use function count;
use Exception;
use function explode;
use function extension_loaded;
use function getcwd;
use function implode;
use function in_array;
use InvalidArgumentException;
use function preg_match;
use function preg_replace;
use function rtrim;
use RuntimeException;
use function sprintf;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strpos;
use function strtolower;
use function substr;
use function substr_replace;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException as SymfonyConsoleInvalidArgumentException;
use Symfony\Component\Console\Exception\Invalid_Option_Exception;
use Symfony\Component\Console\Input\Argv_Input;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
/**
 * Executes tests.
 *
 * Usage:
 *
 * * `codecept run Acceptance`: run all acceptance tests
 * * `codecept run tests/Acceptance/MyCest.php`: run only MyCest
 * * `codecept run Acceptance MyCest`: same as above
 * * `codecept run Acceptance MyCest:myTestInIt`: run one test from a Cest
 * * `codecept run Acceptance MyCest:myTestInIt#1`: run one example or data provider item by number
 * * `codecept run Acceptance MyCest:myTestInIt#1-3`: run a range of examples or data provider items
 * * `codecept run Acceptance MyCest:myTestInIt@name.*`: run data provider items with matching names
 * * `codecept run Acceptance checkout.feature`: run feature-file
 * * `codecept run Acceptance -g slow`: run tests from *slow* group
 * * `codecept run Unit,Functional`: run only unit and functional suites
 *
 * Verbosity modes:
 *
 * * `codecept run -v`:
 * * `codecept run --steps`: print step-by-step execution
 * * `codecept run -vv`: print steps and debug information
 * * `codecept run --debug`: alias for `-vv`
 * * `codecept run -vvv`: print Codeception-internal debug information
 *
 * Load config:
 *
 * * `codecept run -c path/to/another/config`: from another dir
 * * `codecept run -c another_config.yml`: from another config file
 *
 * Override config values:
 *
 * * `codecept run -o "settings: shuffle: true"`: enable shuffle
 * * `codecept run -o "settings: lint: false"`: disable linting
 *
 * Run with specific extension
 *
 * * `codecept run --ext Recorder` run with Recorder extension enabled
 * * `codecept run --ext DotReporter` run with DotReporter printer
 * * `codecept run --ext "My\Custom\Extension"` run with an extension loaded by class name
 *
 * Re-Run failed tests
 *
 * * `codecept run -g failed`
 *
 * Full reference:
 * ```
 * Arguments:
 *  suite                 suite to be tested
 *  test                  test to be run
 *
 * Options:
 *  -o, --override=OVERRIDE Override config values (multiple values allowed)
 *  --config (-c)          Use custom path for config
 *  --report               Show output in compact style
 *  --html                 Generate html with results (default: "report.html")
 *  --xml                  Generate JUnit XML Log (default: "report.xml")
 *  --phpunit-xml          Generate PhpUnit XML Log (default: "phpunit-report.xml")
 *  --no-redirect          Do not redirect to Composer-installed version in vendor/codeception
 *  --colors               Use colors in output
 *  --no-colors            Force no colors in output (useful to override config file)
 *  --silent               Only outputs suite names and final results. Almost the same as `--quiet`
 *  --steps                Show steps in output
 *  --debug (-d)           Alias for `-vv`
 *  --bootstrap            Execute bootstrap script before the test
 *  --coverage             Run with code coverage (default: "coverage.serialized")
 *  --disable-coverage-php Don't generate CodeCoverage report in raw PHP serialized format
 *  --coverage-html        Generate CodeCoverage HTML report in path (default: "coverage")
 *  --coverage-xml         Generate CodeCoverage XML report in file (default: "coverage.xml")
 *  --coverage-text        Generate CodeCoverage text report in file (default: "coverage.txt")
 *  --coverage-phpunit     Generate CodeCoverage PHPUnit report in file (default: "coverage-phpunit")
 *  --coverage-cobertura   Generate CodeCoverage Cobertura report in file (default: "coverage-cobertura")
 *  --no-exit              Don't finish with exit code
 *  --group (-g)           Groups of tests to be executed (multiple values allowed)
 *  --skip (-s)            Skip selected suites (multiple values allowed)
 *  --skip-group (-x)      Skip selected groups (multiple values allowed)
 *  --env                  Run tests in selected environments. (multiple values allowed, environments can be merged with ',')
 *  --fail-fast (-f)       Stop after nth failure (defaults to 1)
 *  --no-rebuild           Do not rebuild actor classes on start
 *  --help (-h)            Display this help message.
 *  --quiet (-q)           Do not output any message. Almost the same as `--silent`
 *  --verbose (-v|vv|vvv)  Increase the verbosity of messages: `v` for normal output, `vv` for steps and debug, `vvv` for Codeception-internal debug
 *  --version (-V)         Display this application version.
 *  --ansi                 Force ANSI output.
 *  --no-ansi              Disable ANSI output.
 *  --no-interaction (-n)  Do not ask any interactive question.
 *  --seed                 Use the given seed for shuffling tests
 * ```
 *
 */
#[As_Command(name: 'run', description: 'Runs the test suites')]
class Run extends Command
{
    use Shared\Config_Trait;
    protected ?Codecept $codecept = null;
    /**
     * @var int Executed suites
     */
    protected int $executed = 0;
    protected array $options = [];
    protected ?Output_Interface $output = null;
    /**
     * Sets Run arguments
     *
     * @throws SymfonyConsoleInvalidArgumentException
     */
    protected function configure(): void
    {
        $this->add_argument('suite', Input_Argument::OPTIONAL, 'suite to be tested')->add_argument('test', Input_Argument::OPTIONAL, 'test to be run')->add_option('override', 'o', Input_Option::VALUE_IS_ARRAY | Input_Option::VALUE_REQUIRED, 'Override config values')->add_option('ext', 'e', Input_Option::VALUE_IS_ARRAY | Input_Option::VALUE_REQUIRED, 'Run with extension enabled')->add_option('report', null, Input_Option::VALUE_NONE, 'Show output in compact style')->add_option('html', null, Input_Option::VALUE_OPTIONAL, 'Generate html with results', 'report.html')->add_option('xml', null, Input_Option::VALUE_OPTIONAL, 'Generate JUnit XML Log', 'report.xml')->add_option('phpunit-xml', null, Input_Option::VALUE_OPTIONAL, 'Generate PhpUnit XML Log', 'phpunit-report.xml')->add_option('colors', null, Input_Option::VALUE_NONE, 'Use colors in output')->add_option('no-colors', null, Input_Option::VALUE_NONE, 'Force no colors in output (useful to override config file)')->add_option('silent', null, Input_Option::VALUE_NONE, 'Only outputs suite names and final results')->add_option('steps', null, Input_Option::VALUE_NONE, 'Show steps in output')->add_option('debug', 'd', Input_Option::VALUE_NONE, 'Show debug and scenario output')->add_option('shard', null, Input_Option::VALUE_REQUIRED, 'Execute subset of tests to run tests on different machine. To split tests on 3 machines to run with shards: 1/3, 2/3, 3/3')->add_option('filter', null, Input_Option::VALUE_REQUIRED, 'Filter tests by name')->add_option('grep', null, Input_Option::VALUE_REQUIRED, 'Filter tests by name (alias to --filter)')->add_option('bootstrap', null, Input_Option::VALUE_OPTIONAL, 'Execute custom PHP script before running tests. Path can be absolute or relative to current working directory', false)->add_option('no-redirect', null, Input_Option::VALUE_NONE, 'Do not redirect to Composer-installed version in vendor/codeception')->add_option('coverage', null, Input_Option::VALUE_OPTIONAL, 'Run with code coverage')->add_option('coverage-html', null, Input_Option::VALUE_OPTIONAL, 'Generate CodeCoverage HTML report in path')->add_option('coverage-xml', null, Input_Option::VALUE_OPTIONAL, 'Generate CodeCoverage XML report in file')->add_option('coverage-text', null, Input_Option::VALUE_OPTIONAL, 'Generate CodeCoverage text report in file')->add_option('coverage-crap4j', null, Input_Option::VALUE_OPTIONAL, 'Generate CodeCoverage report in Crap4J XML format')->add_option('coverage-cobertura', null, Input_Option::VALUE_OPTIONAL, 'Generate CodeCoverage report in Cobertura XML format')->add_option('coverage-phpunit', null, Input_Option::VALUE_OPTIONAL, 'Generate CodeCoverage PHPUnit report in path')->add_option('disable-coverage-php', null, Input_Option::VALUE_NONE, "Don't generate CodeCoverage report in raw PHP serialized format")->add_option('no-exit', null, Input_Option::VALUE_NONE, "Don't finish with exit code")->add_option('group', 'g', Input_Option::VALUE_IS_ARRAY | Input_Option::VALUE_REQUIRED, 'Groups of tests to be executed')->add_option('skip', 's', Input_Option::VALUE_IS_ARRAY | Input_Option::VALUE_REQUIRED, 'Skip selected suites')->add_option('skip-group', 'x', Input_Option::VALUE_IS_ARRAY | Input_Option::VALUE_REQUIRED, 'Skip selected groups')->add_option('env', null, Input_Option::VALUE_IS_ARRAY | Input_Option::VALUE_REQUIRED, 'Run tests in selected environments.')->add_option('fail-fast', 'f', Input_Option::VALUE_OPTIONAL, 'Stop after nth failure')->add_option('no-rebuild', null, Input_Option::VALUE_NONE, 'Do not rebuild actor classes on start')->add_option('seed', null, Input_Option::VALUE_REQUIRED, 'Define random seed for shuffle setting')->add_option('no-artifacts', null, Input_Option::VALUE_NONE, "Don't report about artifacts");
    }
    /**
     * Executes Run
     *
     * @throws ConfigurationException|ParseException
     */
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $this->ensure_php_ext_is_available('CURL');
        $this->ensure_php_ext_is_available('mbstring');
        $this->options = $input->get_options();
        $this->output = $output;
        if ($this->options['bootstrap']) {
            Configuration::load_bootstrap($this->options['bootstrap'], getcwd());
        }
        $config = $this->get_global_config();
        $config = $this->add_runtime_options_to_current_config($config);
        if (!$this->options['colors']) {
            $this->options['colors'] = $config['settings']['colors'];
        }
        if (!$this->options['silent']) {
            $this->output->writeln(Codecept::version_string() . ' https://stand-with-ukraine.pp.ua');
            if ($this->options['seed']) {
                $this->output->writeln('Running with seed: <info>' . $this->options['seed'] . "</info>\n");
            }
        }
        if ($this->options['debug']) {
            $this->output->set_verbosity(Output_Interface::VERBOSITY_VERY_VERBOSE);
        }
        $user_options = array_intersect_key($this->options, array_flip($this->passed_option_keys($input)));
        $user_options = array_merge($user_options, $this->boolean_options($input, ['xml' => 'report.xml', 'phpunit-xml' => 'phpunit-report.xml', 'html' => 'report.html', 'coverage' => 'coverage.serialized', 'coverage-xml' => 'coverage.xml', 'coverage-html' => 'coverage', 'coverage-text' => 'coverage.txt', 'coverage-crap4j' => 'crap4j.xml', 'coverage-cobertura' => 'cobertura.xml', 'coverage-phpunit' => 'coverage-phpunit']));
        $user_options['verbosity'] = $this->output->get_verbosity();
        $user_options['interactive'] = !$input->has_parameter_option(['--no-interaction', '-n']);
        $user_options['ansi'] = (!$input->has_parameter_option('--no-ansi') xor $input->has_parameter_option('ansi'));
        $user_options['disable-coverage-php'] = (bool) $this->options['disable-coverage-php'];
        $user_options['seed'] = $this->options['seed'] ? (int) $this->options['seed'] : random_int(0, mt_getrandmax());
        if ($this->options['no-colors'] || !$user_options['ansi']) {
            $user_options['colors'] = false;
        }
        if ($this->options['group']) {
            $user_options['groups'] = $this->options['group'];
        }
        if ($this->options['skip-group']) {
            $user_options['excludeGroups'] = $this->options['skip-group'];
        }
        if ($this->options['coverage-xml'] || $this->options['coverage-html'] || $this->options['coverage-text'] || $this->options['coverage-crap4j'] || $this->options['coverage-phpunit']) {
            $this->options['coverage'] = true;
        }
        if (!$user_options['ansi'] && $input->get_option('colors')) {
            $user_options['colors'] = true;
            // turn on colors even in non-ansi mode if strictly passed
        }
        // array key will exist if fail-fast option is used
        if (array_key_exists('fail-fast', $user_options)) {
            $user_options['fail-fast'] = (int) $this->options['fail-fast'] ?: 1;
        }
        $suite = (string) $input->get_argument('suite');
        $test = $input->get_argument('test');
        if ($this->options['group']) {
            $this->output->writeln(sprintf('[Groups] <info>%s</info> ', implode(', ', $this->options['group'])));
        }
        if ($input->get_argument('test')) {
            $this->options['steps'] = true;
        }
        if (!$test) {
            // Check if suite is given and is in an included path
            if (!empty($suite) && !empty($config['include'])) {
                $is_include_test = false;
                // Remember original projectDir
                $project_dir = Configuration::project_dir();
                foreach ($config['include'] as $include) {
                    // Find if the suite begins with an include path
                    if (str_starts_with((string) $suite, (string) $include)) {
                        // Use include config
                        $config = Configuration::config($project_dir . $include);
                        $config = $this->add_runtime_options_to_current_config($config);
                        if (!empty($this->options['override'])) {
                            $config = $this->override_config($this->options['override']);
                        }
                        if (!isset($config['paths']['tests'])) {
                            throw new RuntimeException(sprintf("Included '%s' has no tests path configured", $include));
                        }
                        $tests_path = $include . DIRECTORY_SEPARATOR . $config['paths']['tests'];
                        try {
                            [, $suite, $test] = $this->match_test_from_filename($suite, $tests_path);
                            $is_include_test = true;
                        } catch (InvalidArgumentException) {
                            // Incorrect include match, continue trying to find one
                            continue;
                        }
                    } else {
                        $result = $this->match_single_test($suite, $config);
                        if ($result) {
                            [, $suite, $test] = $result;
                        }
                    }
                }
                // Restore main config
                if (!$is_include_test) {
                    $config = $this->add_runtime_options_to_current_config(Configuration::config($project_dir));
                }
            } elseif (!empty($suite)) {
                $result = $this->match_single_test($suite, $config);
                if ($result) {
                    [, $suite, $test] = $result;
                }
            }
        }
        $filter = $input->get_option('filter') ?? $input->get_option('grep') ?? null;
        if ($test) {
            $user_options['filter'] = $this->match_filtered_test_name($test);
        } elseif ($suite && !$this->is_wildcard_suite_name($suite) && !$this->is_suite_in_multi_application($suite)) {
            $user_options['filter'] = $this->match_filtered_test_name($suite);
        }
        if (isset($user_options['filter']) && $filter) {
            throw new Invalid_Option_Exception("--filter and --grep can't be used with a test name");
        }
        if ($filter) {
            $user_options['filter'] = $filter;
        }
        if ($this->options['shard']) {
            $this->output->writeln("[Shard {$user_options['shard']}] <info>Running subset of tests</info>");
        }
        if (!$this->options['silent'] && $config['settings']['shuffle']) {
            $this->output->writeln('[Seed] <info>' . $user_options['seed'] . '</info>');
        }
        $this->codecept = new Codecept($user_options);
        if ($suite && $test) {
            $this->codecept->run($suite, $test, $config);
        }
        // Run all tests of given suite or all suites
        if (!$test) {
            $did_pass_cli_suite = !empty($suite);
            $raw_suites = $did_pass_cli_suite ? explode(',', (string) $suite) : Configuration::suites();
            /** @var string[] $mainAppSuites */
            $main_app_suites = [];
            /** @var array<string,string> $appSpecificSuites */
            $app_specific_suites = [];
            /** @var string[] $wildcardSuites */
            $wildcard_suites = [];
            foreach ($raw_suites as $raw_suite) {
                if ($this->is_wildcard_suite_name($raw_suite)) {
                    $wildcard_suites[] = explode('*::', $raw_suite)[1];
                    continue;
                }
                if ($this->is_suite_in_multi_application($raw_suite)) {
                    $app_and_suite = explode('::', $raw_suite);
                    $app_specific_suites[$app_and_suite[0]][] = $app_and_suite[1];
                    continue;
                }
                $main_app_suites[] = $raw_suite;
            }
            if ([] !== $main_app_suites) {
                $this->executed = $this->run_suites($main_app_suites, $this->options['skip']);
            }
            if (!empty($wildcard_suites) && !empty($app_specific_suites)) {
                $this->output->write_ln('<error>Wildcard options can not be combined with specific suites of included apps.</error>');
                return Command::INVALID;
            }
            if (!empty($config['include']) && (!$did_pass_cli_suite || !empty($wildcard_suites) || !empty($app_specific_suites))) {
                $current_dir = Configuration::project_dir();
                $included_apps = $config['include'];
                if (!empty($app_specific_suites)) {
                    $included_apps = array_intersect($included_apps, array_keys($app_specific_suites));
                }
                $this->run_included_suites($included_apps, $current_dir, $app_specific_suites, $wildcard_suites);
            }
            if ($this->executed === 0) {
                throw new RuntimeException(sprintf("Suite '%s' could not be found", implode(', ', $raw_suites)));
            }
        }
        $this->codecept->print_result();
        if ($this->options['shard']) {
            $this->output->writeln("[Shard {$user_options['shard']}] <info>Merge this result with other shards to see the complete report</info>");
        }
        if (!$input->get_option('no-exit') && !$this->codecept->get_result_aggregator()->was_successful_ignoring_warnings()) {
            exit(1);
        }
        return Command::SUCCESS;
    }
    protected function match_single_test(string $suite, array $config): ?array
    {
        // Workaround when codeception.yml is inside tests directory and tests path is set to "."
        // @see https://github.com/Codeception/Codeception/issues/4432
        if (isset($config['paths']['tests']) && $config['paths']['tests'] === '.' && !preg_match('#^\.[/\\\\]#', $suite)) {
            $suite = './' . $suite;
        }
        // running a single test when suite has a configured path
        if (isset($config['suites'])) {
            foreach ($config['suites'] as $s => $suite_config) {
                if (!isset($suite_config['path'])) {
                    continue;
                }
                $tests_path = $config['paths']['tests'] . DIRECTORY_SEPARATOR . $suite_config['path'];
                if ($suite_config['path'] === '.') {
                    $tests_path = $config['paths']['tests'];
                }
                if (preg_match("#^{$tests_path}/(.*?)\$#", $suite, $matches)) {
                    $matches[2] = $matches[1];
                    $matches[1] = $s;
                    return $matches;
                }
            }
        }
        if (!Configuration::is_empty()) {
            // Run single test without included tests
            if (str_starts_with($suite, (string) $config['paths']['tests'])) {
                return $this->match_test_from_filename($suite, $config['paths']['tests']);
            }
            // Run single test from working directory
            $real_test_dir = (string) realpath(Configuration::tests_dir());
            $cwd = (string) getcwd();
            if (str_starts_with($real_test_dir, $cwd)) {
                $file = $suite;
                if (str_contains($file, ':')) {
                    [$file] = explode(':', $suite, -1);
                }
                $real_path = $cwd . DIRECTORY_SEPARATOR . $file;
                if (file_exists($real_path) && str_starts_with($real_path, $real_test_dir)) {
                    //only match test if file is in tests directory
                    return $this->match_test_from_filename($cwd . DIRECTORY_SEPARATOR . $suite, $real_test_dir);
                }
            }
        }
        return null;
    }
    /**
     * Runs included suites recursively
     *
     * @param string[] $suites
     * @param array<string,string[]> $filterAppSuites An array keyed by included app name where values are suite names to run.
     * @param string[] $filterSuitesByWildcard A list of suite names (applies to all included apps)
     * @throws ConfigurationException
     */
    protected function run_included_suites(array $suites, string $parent_dir, array $filter_app_suites = [], array $filter_suites_by_wildcard = []): void
    {
        $default_config = Configuration::config();
        $absolute_path = Configuration::project_dir();
        foreach ($suites as $relative_path) {
            $current_dir = rtrim($parent_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relative_path;
            $config = Configuration::config($current_dir);
            if (!empty($default_config['groups'])) {
                $groups = array_map(fn($g): string => $absolute_path . $g, $default_config['groups']);
                Configuration::append(['groups' => $groups]);
            }
            $suites = Configuration::suites();
            if ($filter_suites_by_wildcard !== []) {
                $suites = array_intersect($suites, $filter_suites_by_wildcard);
            }
            if (isset($filter_app_suites[$relative_path])) {
                $suites = array_intersect($suites, $filter_app_suites[$relative_path]);
            }
            $namespace = $this->current_namespace();
            $this->output->writeln("\n<fg=white;bg=magenta>\n[{$namespace}]: tests from {$current_dir}\n</fg=white;bg=magenta>");
            $this->executed += $this->run_suites($suites, $this->options['skip']);
            if (!empty($config['include'])) {
                $this->run_included_suites($config['include'], $current_dir);
            }
        }
    }
    protected function current_namespace(): string
    {
        $config = Configuration::config();
        if (!$config['namespace']) {
            throw new RuntimeException("Can't include into runner suite without a namespace;\n" . 'Please add `namespace` section into included codeception.yml file');
        }
        return $config['namespace'];
    }
    /**
     * @param string[] $suites
     * @param string[] $skippedSuites
     * @return int Number of executed test suites
     */
    protected function run_suites(array $suites, array $skipped_suites = []): int
    {
        $executed = 0;
        foreach ($suites as $suite) {
            if (in_array($suite, $skipped_suites)) {
                continue;
            }
            if (!in_array($suite, Configuration::suites())) {
                continue;
            }
            $this->codecept->run($suite);
            ++$executed;
        }
        return $executed;
    }
    /**
     * @return string[]
     */
    protected function match_test_from_filename(string $filename, string $tests_path): array
    {
        $filter = '';
        if (str_contains($filename, ':')) {
            if ((PHP_OS === 'Windows' || PHP_OS === 'WINNT') && $filename[1] === ':') {
                // match C:\...
                [$drive, $path, $filter] = explode(':', $filename, 3);
                $filename = $drive . ':' . $path;
            } else {
                [$filename, $filter] = explode(':', $filename, 2);
            }
            if ($filter !== '') {
                $filter = ':' . $filter;
            }
        }
        $tests_path = str_replace(['//', '\/', '\\'], '/', $tests_path);
        $filename = str_replace(['//', '\/', '\\'], '/', $filename);
        if (rtrim($filename, '/') === $tests_path) {
            //codecept run tests
            return ['', '', $filter];
        }
        $res = preg_match("#^{$tests_path}/(.*?)(?>/(.*))?\$#", $filename, $matches);
        if (!$res) {
            throw new InvalidArgumentException("Test file can't be matched");
        }
        if (!isset($matches[2])) {
            $matches[2] = '';
        }
        if ($filter !== '') {
            $matches[2] .= $filter;
        }
        return $matches;
    }
    private function match_filtered_test_name(string &$path): ?string
    {
        $test_parts = explode(':', $path, 2);
        if (count($test_parts) > 1) {
            [$path, $filter] = $test_parts;
            // use carat to signify start of string like in normal regex
            // phpunit --filter matches against the fully qualified method name, so tests actually begin with :
            $carat_pos = strpos($filter, '^');
            if ($carat_pos !== false) {
                return substr_replace($filter, ':', $carat_pos, 1);
            }
            return $filter;
        }
        return null;
    }
    /**
     * @return string[]
     */
    protected function passed_option_keys(Argv_Input $input): array
    {
        $options = [];
        $request = (string) $input;
        $tokens = explode(' ', $request);
        foreach ($tokens as $token) {
            $token = preg_replace('#=.*#', '', $token);
            // strip = from options
            if (empty($token)) {
                continue;
            }
            if ($token == '--') {
                break;
                // there should be no options after ' -- ', only arguments
            }
            if (str_starts_with($token, '--')) {
                $options[] = substr($token, 2);
            } elseif ($token[0] === '-') {
                $short_option = substr($token, 1);
                $options[] = $this->get_definition()->get_option_for_shortcut($short_option)->get_name();
            }
        }
        return $options;
    }
    /**
     * @return array<string, bool>
     */
    protected function boolean_options(Argv_Input $input, array $options = []): array
    {
        $values = [];
        $request = (string) $input;
        foreach ($options as $option => $default_value) {
            if (strpos($request, sprintf('--%s', $option))) {
                $values[$option] = $input->get_option($option) ?: $default_value;
            } else {
                $values[$option] = false;
            }
        }
        return $values;
    }
    /**
     * @throws Exception
     */
    private function ensure_php_ext_is_available(string $ext): void
    {
        if (!extension_loaded(strtolower($ext))) {
            throw new Exception("Codeception requires \"{$ext}\" extension installed to make tests run\n" . "If you are not sure, how to install \"{$ext}\", please refer to StackOverflow\n\n" . "Notice: PHP for Apache/Nginx and CLI can have different php.ini files.\n" . "Please make sure that your PHP you run from console has \"{$ext}\" enabled.");
        }
    }
    private function is_wildcard_suite_name(string $suite_name): bool
    {
        return str_starts_with($suite_name, '*::');
    }
    private function is_suite_in_multi_application(string $suite_name): bool
    {
        return str_contains($suite_name, '::');
    }
    private function add_runtime_options_to_current_config(array $config): array
    {
        // update config from options
        if (count($this->options['override']) > 0) {
            $config = $this->override_config($this->options['override']);
        }
        // enable extensions
        if ($this->options['ext']) {
            return $this->enable_extensions($this->options['ext']);
        }
        return $config;
    }
}
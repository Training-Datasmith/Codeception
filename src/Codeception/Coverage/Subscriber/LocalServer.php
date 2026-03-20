<?php

declare (strict_types=1);
namespace Codeception\Coverage\Subscriber;

use function array_filter;
use function array_replace_recursive;
use Codeception\Configuration;
use Codeception\Coverage\Suite_Subscriber;
use Codeception\Event\Step_Event;
use Codeception\Event\Suite_Event;
use Codeception\Event\Test_Event;
use Codeception\Events;
use Codeception\Exception\Module_Exception;
use Codeception\Exception\Remote_Exception;
use Codeception\Lib\Interfaces\Web as WebInterface;
use Codeception\Lib\Notification;
use Codeception\Module\Web_Driver as WebDriverModule;
use Facebook\Web_Driver\Exception\No_Such_Alert_Exception;
use function file_exists;
use function file_get_contents;
use function json_encode;
use function parse_url;
use function preg_match;
use RuntimeException;
use Sebastian_Bergmann\Code_Coverage\Code_Coverage;
use function str_replace;
use function stream_context_create;
use function unserialize;
use function usleep;
/**
 * When collecting code coverage data from local server HTTP requests are sent to c3.php file.
 * Coverage Collection is started by sending cookies/headers.
 * Result is taken from the local file and merged with local code coverage results.
 *
 * Class LocalServer
 * @package Codeception\Coverage\Subscriber
 */
class Local_Server extends Suite_Subscriber
{
    // headers
    /**
     * @var string
     */
    public const COVERAGE_HEADER = 'X-Codeception-CodeCoverage';
    /**
     * @var string
     */
    public const COVERAGE_HEADER_ERROR = 'X-Codeception-CodeCoverage-Error';
    /**
     * @var string
     */
    public const COVERAGE_HEADER_CONFIG = 'X-Codeception-CodeCoverage-Config';
    /**
     * @var string
     */
    public const COVERAGE_HEADER_SUITE = 'X-Codeception-CodeCoverage-Suite';
    // cookie names
    /**
     * @var string
     */
    public const COVERAGE_COOKIE = 'CODECEPTION_CODECOVERAGE';
    /**
     * @var string
     */
    public const COVERAGE_COOKIE_ERROR = 'CODECEPTION_CODECOVERAGE_ERROR';
    protected string $suite_name = '';
    protected array $c3Access = ['http' => ['method' => 'GET', 'header' => '']];
    protected ?Web_Interface $module = null;
    /**
     * @var array<string, string>
     */
    public static array $events = [Events::SUITE_BEFORE => 'beforeSuite', Events::TEST_BEFORE => 'beforeTest', Events::STEP_AFTER => 'afterStep', Events::SUITE_AFTER => 'afterSuite'];
    protected function is_enabled(): bool
    {
        return $this->module instanceof Web_Interface && !$this->settings['remote'] && $this->settings['enabled'];
    }
    public function before_suite(Suite_Event $event): void
    {
        $this->module = $this->get_server_connection_module($event->get_suite()->get_modules());
        $this->apply_settings($event->get_settings());
        if (!$this->is_enabled()) {
            return;
        }
        $this->suite_name = $event->get_suite()->get_base_name();
        if ($this->settings['remote_config']) {
            $this->add_c3access_header(self::COVERAGE_HEADER_CONFIG, $this->settings['remote_config']);
            if ($this->c3Request('clear') === false) {
                throw new Remote_Exception('
                    CodeCoverage Error.
                    Check the file "c3.php" is included in your application.
                    We tried to access "/c3/report/clear" but this URI was not accessible.
                    You can review actual error messages in c3tmp dir.
                    ');
            }
        }
    }
    public function before_test(Test_Event $event): void
    {
        if (!$this->is_enabled()) {
            return;
        }
        $this->start_coverage_collection($event->get_test()->get_name());
    }
    public function after_step(Step_Event $event): void
    {
        if (!$this->is_enabled()) {
            return;
        }
        $this->fetch_errors();
    }
    public function after_suite(Suite_Event $event): void
    {
        if (!$this->is_enabled()) {
            return;
        }
        $output_dir = Configuration::output_dir() . 'c3tmp/';
        $block_file = $output_dir . 'block_report';
        $coverage_file = $output_dir . 'codecoverage.serialized';
        $error_file = $output_dir . 'error.txt';
        $this->wait_for_file($block_file, 120, 250000);
        $this->wait_for_file($coverage_file, 5, 500000);
        if (!file_exists($coverage_file)) {
            throw new RuntimeException(file_exists($error_file) ? file_get_contents($error_file) : "Code coverage file {$coverage_file} does not exist");
        }
        if ($coverage = @unserialize(file_get_contents($coverage_file))) {
            $this->pre_process_coverage($coverage)->merge_to_print($coverage);
        }
    }
    /**
     * Allows Translating Remote Paths To Local (IE: When Using Docker)
     */
    protected function pre_process_coverage(Code_Coverage $coverage): self
    {
        if (!$this->settings['work_dir']) {
            return $this;
        }
        $work_dir = rtrim((string) $this->settings['work_dir'], '/\\') . DIRECTORY_SEPARATOR;
        $project_dir = Configuration::project_dir();
        $coverage_data = $coverage->get_data(true);
        // We only want covered files, not all whitelisted ones.
        codecept_debug("Replacing all instances of {$work_dir} with {$project_dir}");
        foreach ($coverage_data as $path => $datum) {
            unset($coverage_data[$path]);
            $path = str_replace($work_dir, $project_dir, (string) $path);
            $coverage_data[$path] = $datum;
        }
        $coverage->set_data($coverage_data);
        return $this;
    }
    protected function c3Request(string $action): string|false
    {
        $this->add_c3access_header(self::COVERAGE_HEADER, 'remote-access');
        $context = stream_context_create($this->c3Access);
        $c3Url = $this->settings['c3_url'] ?? $this->module->_get_url();
        $contents = file_get_contents("{$c3Url}/c3/report/{$action}", false, $context);
        // $http_response_header is deprecated as of PHP 8.5
        if (function_exists('http_get_last_response_headers')) {
            $http_response_header = http_get_last_response_headers();
        }
        $ok_headers = array_filter($http_response_header, fn($h): int|false => preg_match('#^HTTP(.*?)\s200#', (string) $h));
        if ($ok_headers === []) {
            throw new Remote_Exception('Request was not successful. See response header: ' . $http_response_header[0]);
        }
        if ($contents === false) {
            $this->get_remote_error($http_response_header);
        }
        return $contents;
    }
    protected function start_coverage_collection(string $test_name): void
    {
        $coverage_data_json = json_encode(['CodeCoverage' => $test_name, 'CodeCoverage_Suite' => $this->suite_name, 'CodeCoverage_Config' => $this->settings['remote_config']], JSON_THROW_ON_ERROR);
        if ($this->module instanceof Web_Driver_Module) {
            $this->module->am_on_page('/');
        }
        $cookie_domain = $this->settings['cookie_domain'] ?? parse_url($this->settings['c3_url'] ?? $this->module->_get_url(), PHP_URL_HOST) ?? 'localhost';
        if (!$cookie_domain) {
            // we need to separate coverage cookies by host; we can't separate cookies by port.
            $cookie_domain = 'localhost';
        }
        $cookie_params = $cookie_domain !== 'localhost' ? ['domain' => $cookie_domain] : [];
        $this->module->set_cookie(self::COVERAGE_COOKIE, $coverage_data_json, $cookie_params);
        // putting in configuration ensures the cookie is used for all sessions of a MultiSession test
        $cookies = $this->module->_get_config('cookies');
        if (!is_array($cookies)) {
            $cookies = [];
        }
        $cookie_updated = false;
        foreach ($cookies as &$cookie) {
            if (isset($cookie['Name'], $cookie['Value']) && $cookie['Name'] === self::COVERAGE_COOKIE) {
                $cookie['Value'] = $coverage_data_json;
                $cookie_updated = true;
                break;
            }
            // \Codeception\Lib\InnerBrowser will complain about this
        }
        unset($cookie);
        if (!$cookie_updated) {
            $cookies[] = ['Name' => self::COVERAGE_COOKIE, 'Value' => $coverage_data_json];
        }
        $this->module->_set_config(['cookies' => $cookies]);
    }
    protected function fetch_errors(): void
    {
        // Calling grabCookie() while an alert is present dismisses the alert
        // @see https://github.com/Codeception/Codeception/issues/1485
        if ($this->module instanceof Web_Driver_Module) {
            try {
                $this->module->web_driver->switch_to()->alert()->get_text();
                // If this succeeds an alert is present, abort
                return;
            } catch (No_Such_Alert_Exception) {
                // No alert present, continue
            }
        }
        try {
            $error = $this->module->grab_cookie(self::COVERAGE_COOKIE_ERROR);
        } catch (Module_Exception) {
            // when a new session is started we can't get cookies because there is no
            // current page, but there can be no code coverage error either
            return;
        }
        if (!empty($error)) {
            $this->module->reset_cookie(self::COVERAGE_COOKIE_ERROR);
            throw new Remote_Exception($error);
        }
    }
    /** @param string[] $headers */
    protected function get_remote_error(array $headers): void
    {
        foreach ($headers as $header) {
            if (str_starts_with($header, self::COVERAGE_HEADER_ERROR)) {
                throw new Remote_Exception($header);
            }
        }
    }
    protected function add_c3access_header(string $header, string $value): void
    {
        $header_string = "{$header}: {$value}\r\n";
        if (!str_contains((string) $this->c3Access['http']['header'], $header_string)) {
            $this->c3Access['http']['header'] .= $header_string;
        }
    }
    protected function apply_settings(array $settings): void
    {
        parent::apply_settings($settings);
        if (isset($settings['coverage']['remote_context_options'])) {
            $this->c3Access = array_replace_recursive($this->c3Access, $settings['coverage']['remote_context_options']);
        }
    }
    private function wait_for_file(string $file, int $max_retries, int $sleep_time): void
    {
        $retries = $max_retries;
        while ($retries > 0 && (!file_exists($file) || file_get_contents($file) !== '0')) {
            usleep($sleep_time);
            --$retries;
        }
        if (!file_exists($file) || file_get_contents($file) !== '0') {
            Notification::warning('Timeout: Some coverage data is not included in the coverage report.', '');
        }
    }
}
<?php

declare (strict_types=1);
namespace Codeception\Coverage;

use function array_keys;
use Codeception\Configuration;
use Codeception\Coverage\Subscriber\Printer;
use Codeception\Exception\Configuration_Exception;
use Codeception\Lib\Interfaces\Remote as RemoteInterface;
use Codeception\Subscriber\Shared\Static_Events_Trait;
use Exception;
use Php_Unit\Framework\Code_Coverage_Exception;
use Sebastian_Bergmann\Code_Coverage\Code_Coverage;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
abstract class Suite_Subscriber implements Event_Subscriber_Interface
{
    use Static_Events_Trait;
    protected array $default_settings = ['enabled' => false, 'remote' => false, 'local' => false, 'xdebug_session' => 'codeception', 'remote_config' => null, 'show_uncovered' => false, 'c3_url' => null, 'work_dir' => null, 'cookie_domain' => null, 'path_coverage' => false, 'strict_covers_annotation' => false, 'ignore_deprecated_code' => false, 'disable_code_coverage_ignore' => false];
    protected array $settings = [];
    protected array $filters = [];
    protected array $modules = [];
    protected ?Code_Coverage $coverage = null;
    protected string $log_dir;
    public static array $events = [];
    abstract protected function is_enabled();
    /**
     * SuiteSubscriber constructor.
     *
     * @throws ConfigurationException
     */
    public function __construct(protected array $options = [])
    {
        $this->log_dir = Configuration::output_dir();
    }
    /**
     * @throws Exception
     */
    protected function apply_settings(array $settings): void
    {
        try {
            $this->coverage = Php_Code_Coverage_Factory::build();
        } catch (Code_Coverage_Exception $e) {
            throw new Exception('XDebug is required to collect CodeCoverage. Please install xdebug extension and enable it in php.ini', $e->get_code(), $e);
        }
        $this->filters = $settings;
        $this->settings = $this->default_settings;
        $keys = array_keys($this->default_settings);
        foreach ($keys as $key) {
            if (isset($settings['coverage'][$key])) {
                $this->settings[$key] = $settings['coverage'][$key];
            }
        }
        $this->configure_coverage();
    }
    protected function configure_coverage(): void
    {
        if ($this->settings['strict_covers_annotation']) {
            $this->coverage->enable_check_for_unintentionally_covered_code();
        }
        if ($this->settings['ignore_deprecated_code']) {
            $this->coverage->ignore_deprecated_code();
        } else {
            $this->coverage->do_not_ignore_deprecated_code();
        }
        if ($this->settings['disable_code_coverage_ignore']) {
            $this->coverage->disable_annotations_for_ignoring_code();
        } else {
            $this->coverage->enable_annotations_for_ignoring_code();
        }
        if ($this->settings['show_uncovered']) {
            $this->coverage->include_uncovered_files();
        } else {
            $this->coverage->exclude_uncovered_files();
        }
    }
    protected function get_server_connection_module(array $modules): ?Remote_Interface
    {
        foreach ($modules as $module) {
            if ($module instanceof Remote_Interface) {
                return $module;
            }
        }
        return null;
    }
    protected function merge_to_print(Code_Coverage $coverage): void
    {
        Printer::$coverage->merge($coverage);
    }
}
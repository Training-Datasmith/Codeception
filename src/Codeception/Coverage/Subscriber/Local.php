<?php

declare (strict_types=1);
namespace Codeception\Coverage\Subscriber;

use Codeception\Coverage\Filter;
use Codeception\Coverage\Php_Code_Coverage_Factory;
use Codeception\Coverage\Suite_Subscriber;
use Codeception\Event\Suite_Event;
use Codeception\Events;
use Codeception\Exception\Configuration_Exception;
use Codeception\Exception\Module_Exception;
use Codeception\Lib\Interfaces\Remote;
use Exception;
/**
 * Collects code coverage from unit and functional tests.
 * Results from all suites are merged.
 */
class Local extends Suite_Subscriber
{
    /**
     * @var array<string, string>
     */
    public static array $events = [Events::SUITE_BEFORE => 'beforeSuite', Events::SUITE_AFTER => 'afterSuite'];
    protected ?Remote $module = null;
    protected function is_enabled(): bool
    {
        return !$this->module instanceof Remote && $this->settings['enabled'];
    }
    /**
     * @throws ConfigurationException|ModuleException|Exception
     */
    public function before_suite(Suite_Event $event): void
    {
        $this->apply_settings($event->get_settings());
        $this->module = $this->get_server_connection_module($event->get_suite()->get_modules());
        if (!$this->is_enabled()) {
            return;
        }
        $event->get_suite()->collect_code_coverage(true);
        Filter::setup($this->coverage)->white_list($this->filters)->black_list($this->filters);
    }
    public function after_suite(Suite_Event $event): void
    {
        if (!$this->is_enabled()) {
            return;
        }
        $code_coverage = Php_Code_Coverage_Factory::build();
        Php_Code_Coverage_Factory::clear();
        $this->merge_to_print($code_coverage);
    }
}
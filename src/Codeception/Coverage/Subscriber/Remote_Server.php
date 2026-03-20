<?php

declare (strict_types=1);
namespace Codeception\Coverage\Subscriber;

use Codeception\Configuration;
use Codeception\Event\Suite_Event;
use Codeception\Lib\Interfaces\Web;
use Codeception\Util\File_System;
use function file_put_contents;
use function is_dir;
use function mkdir;
use Phar_Data;
use function strtr;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;
/**
 * When collecting code coverage on remote server
 * data is retrieved over HTTP and not merged with the local code coverage results.
 *
 * Class RemoteServer
 * @package Codeception\Coverage\Subscriber
 */
class Remote_Server extends Local_Server
{
    public function is_enabled(): bool
    {
        return $this->module instanceof Web && $this->settings['remote'] && $this->settings['enabled'];
    }
    public function after_suite(Suite_Event $event): void
    {
        if (!$this->is_enabled()) {
            return;
        }
        $suite = strtr($event->get_suite()->get_name(), ['\\' => '.']);
        if ($this->options['coverage-xml']) {
            $this->retrieve_and_print('clover', $suite, '.remote.coverage.xml');
        }
        if ($this->options['coverage-html']) {
            $this->retrieve_to_temp_file_and_print('html', $suite, '.remote.coverage');
        }
        if ($this->options['coverage-crap4j']) {
            $this->retrieve_and_print('crap4j', $suite, '.remote.crap4j.xml');
        }
        if ($this->options['coverage-cobertura']) {
            $this->retrieve_and_print('cobertura', $suite, '.remote.cobertura.xml');
        }
        if ($this->options['coverage-phpunit']) {
            $this->retrieve_to_temp_file_and_print('phpunit', $suite, '.remote.coverage-phpunit');
        }
    }
    protected function retrieve_and_print(string $type, string $suite, string $extension): void
    {
        $dest_file = Configuration::output_dir() . $suite . $extension;
        file_put_contents($dest_file, $this->c3Request($type));
    }
    protected function retrieve_to_temp_file_and_print(string $type, string $suite, string $extension): void
    {
        $temp_file = tempnam(sys_get_temp_dir(), 'C3') . '.tar';
        file_put_contents($temp_file, $this->c3Request($type));
        $dest_dir = Configuration::output_dir() . $suite . $extension;
        if (is_dir($dest_dir)) {
            File_System::do_empty_dir($dest_dir);
        } else {
            mkdir($dest_dir, 0777, true);
        }
        $phar_data = new Phar_Data($temp_file);
        $phar_data->extract_to($dest_dir);
        unlink($temp_file);
    }
}
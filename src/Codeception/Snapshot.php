<?php

declare (strict_types=1);
namespace Codeception;

use Codeception\Exception\Content_Not_Found;
use Codeception\Util\Debug;
use Codeception\Util\Shared\Asserts;
use Php_Unit\Framework\Assertion_Failed_Error;
abstract class Snapshot
{
    use Asserts;
    protected ?string $file_name = null;
    /**
     * @var string|false
     */
    protected $data_set;
    protected ?bool $refresh = null;
    protected bool $show_diff = false;
    protected bool $save_as_json = true;
    protected string $extension = 'json';
    /**
     * Should return data from current test run
     */
    abstract protected function fetch_data(): array|string|false;
    /**
     * Performs assertion on saved data set against current dataset.
     * Can be overridden to implement custom assertion
     */
    protected function assert_data(mixed $data): void
    {
        $this->assert_same($this->data_set, $data, "Snapshot doesn't match real data");
    }
    /**
     * Loads data set from file.
     */
    protected function load(): void
    {
        $path = $this->get_file_name();
        if (!is_file($path)) {
            return;
        }
        $contents = file_get_contents($path);
        $this->data_set = $this->save_as_json ? json_decode($contents, false, 512, JSON_THROW_ON_ERROR) : $contents;
        if ($this->data_set === null || $this->data_set === false) {
            throw new Content_Not_Found('Loaded snapshot is empty');
        }
    }
    /**
     * Saves data set to file
     */
    protected function save(): void
    {
        $contents = $this->save_as_json ? json_encode($this->data_set, JSON_THROW_ON_ERROR) : $this->data_set;
        file_put_contents($this->get_file_name(), $contents);
    }
    /**
     * If no filename is defined, generates one from class name
     */
    protected function get_file_name(): string
    {
        return codecept_data_dir() . $this->file_name ??= preg_replace('#\W#', '.', static::class) . '.' . $this->extension;
    }
    /**
     * Performs assertion for data sets
     */
    public function assert(): void
    {
        $data = $this->fetch_data();
        if (!$data) {
            throw new Content_Not_Found('Fetched snapshot is empty.');
        }
        $this->load();
        if (!$this->data_set) {
            $this->print_debug('Snapshot is empty. Updating snapshot...');
            $this->data_set = $data;
            $this->save();
            return;
        }
        try {
            $this->assert_data($data);
            $this->print_debug('Data matches snapshot');
        } catch (Assertion_Failed_Error $exception) {
            $this->print_debug('Snapshot assertion failed');
            $confirm = is_bool($this->refresh) ? $this->refresh : Debug::confirm('Should we update snapshot with fresh data? (Y/n) ');
            if ($confirm) {
                $this->data_set = $data;
                $this->save();
                $this->print_debug('Snapshot data updated');
                return;
            }
            if ($this->show_diff) {
                throw $exception;
            }
            $this->fail($exception->get_message());
        }
    }
    /**
     * Force update snapshot data.
     */
    public function should_refresh_snapshot(bool $refresh = true): void
    {
        $this->refresh = $refresh;
    }
    /**
     * Show detailed diff if snapshot test fails
     */
    public function should_show_diff_on_fail(bool $show_diff = true): void
    {
        $this->show_diff = $show_diff;
    }
    /**
     * json_encode/json_decode the snapshot data on storing/reading.
     */
    public function should_save_as_json(bool $save_as_json = true): void
    {
        $this->save_as_json = $save_as_json;
    }
    /**
     * Set the snapshot file extension.
     * By default it will be stored as `.json`.
     *
     * The file extension will not perform any formatting in the data,
     * it is only used as the snapshot file extension.
     */
    public function set_snapshot_file_extension(string $file_extension = 'json'): void
    {
        $this->extension = $file_extension;
    }
    private function print_debug(string $message): void
    {
        Debug::debug(static::class . ': ' . $message);
    }
}
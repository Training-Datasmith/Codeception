<?php

declare (strict_types=1);
namespace Codeception\Coverage\Subscriber;

use function array_merge;
use Codeception\Configuration;
use Codeception\Coverage\Filter;
use Codeception\Coverage\Php_Code_Coverage_Factory;
use Codeception\Event\Print_Result_Event;
use Codeception\Events;
use Codeception\Lib\Console\Output;
use Codeception\Subscriber\Shared\Static_Events_Trait;
use function file_put_contents;
use Php_Unit\Runner\Version as PHPUnitVersion;
use Sebastian_Bergmann\Code_Coverage\Code_Coverage;
use Sebastian_Bergmann\Code_Coverage\Report\Clover as CloverReport;
use Sebastian_Bergmann\Code_Coverage\Report\Cobertura as CoberturaReport;
use Sebastian_Bergmann\Code_Coverage\Report\Crap4j as Crap4jReport;
use Sebastian_Bergmann\Code_Coverage\Report\Html\Facade as HtmlFacadeReport;
use Sebastian_Bergmann\Code_Coverage\Report\PHP as PhpReport;
use Sebastian_Bergmann\Code_Coverage\Report\Text as TextReport;
use Sebastian_Bergmann\Code_Coverage\Report\Thresholds;
use Sebastian_Bergmann\Code_Coverage\Report\Xml\Facade as XmlFacadeReport;
use function str_starts_with;
use function strpos;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
class Printer implements Event_Subscriber_Interface
{
    use Static_Events_Trait;
    /**
     * @var array<string, string>
     */
    public static array $events = [Events::RESULT_PRINT_AFTER => 'printResult'];
    protected array $settings = ['enabled' => true, 'low_limit' => 35, 'high_limit' => 70, 'show_uncovered' => false, 'show_only_summary' => false];
    public static Code_Coverage $coverage;
    protected string $log_dir;
    public function __construct(protected array $options, private readonly Output $output)
    {
        $this->log_dir = Configuration::output_dir();
        $this->settings = array_merge($this->settings, Configuration::config()['coverage']);
        self::$coverage = Php_Code_Coverage_Factory::build();
        // Apply filter
        $filter = new Filter(self::$coverage);
        $filter->white_list(Configuration::config());
        $filter->black_list(Configuration::config());
    }
    protected function absolute_path(string $path): string
    {
        if (str_starts_with($path, '/') || strpos($path, ':') === 1) {
            // absolute path
            return $path;
        }
        return $this->log_dir . $path;
    }
    public function print_result(Print_Result_Event $event): void
    {
        if (!$this->settings['enabled']) {
            $this->output->write("\nCodeCoverage is disabled in `codeception.yml` config\n");
            return;
        }
        if (!$this->options['quiet']) {
            $this->print_console();
        }
        $this->output->write("Remote CodeCoverage reports are not printed to console\n");
        if ($this->options['disable-coverage-php'] === true) {
            $this->output->write("PHP serialized report was skipped\n");
        } else {
            $this->print_php();
        }
        $this->output->write("\n");
        $reports = ['HTML' => ['name' => 'coverage-html', 'method' => 'printHTML'], 'XML' => ['name' => 'coverage-xml', 'method' => 'printXML'], 'Text' => ['name' => 'coverage-text', 'method' => 'printText'], 'Crap4j' => ['name' => 'coverage-crap4j', 'method' => 'printCrap4j'], 'Cobertura' => ['name' => 'coverage-cobertura', 'method' => 'printCobertura'], 'PHPUnit' => ['name' => 'coverage-phpunit', 'method' => 'printPHPUnit']];
        foreach ($reports as $report_type => $report_data) {
            if ($option = $this->options[$report_data['name']]) {
                $this->{$report_data['method']}();
                $this->output->write("{$report_type} report generated in {$option}\n");
            }
        }
    }
    protected function print_console(): void
    {
        $writer = $this->create_text_writer();
        $this->output->write($writer->process(self::$coverage, $this->options['colors']));
    }
    protected function print_html(): void
    {
        $writer = $this->create_html_facade_writer();
        $writer->process(self::$coverage, $this->absolute_path($this->options['coverage-html']));
    }
    protected function print_xml(): void
    {
        $writer = new Clover_Report();
        $writer->process(self::$coverage, $this->absolute_path($this->options['coverage-xml']));
    }
    protected function print_php(): void
    {
        $writer = new Php_Report();
        $writer->process(self::$coverage, $this->absolute_path($this->options['coverage']));
    }
    protected function print_text(): void
    {
        $writer = $this->create_text_writer();
        file_put_contents($this->absolute_path($this->options['coverage-text']), $writer->process(self::$coverage));
    }
    protected function print_crap4j(): void
    {
        $writer = new Crap4j_Report();
        $writer->process(self::$coverage, $this->absolute_path($this->options['coverage-crap4j']));
    }
    protected function print_cobertura(): void
    {
        $writer = new Cobertura_Report();
        $writer->process(self::$coverage, $this->absolute_path($this->options['coverage-cobertura']));
    }
    protected function print_php_unit(): void
    {
        $writer = new Xml_Facade_Report(Php_Unit_Version::id());
        $writer->process(self::$coverage, $this->absolute_path($this->options['coverage-phpunit']));
    }
    private function create_html_facade_writer(): Html_Facade_Report
    {
        $generator = ', <a href="https://codeception.com">Codeception</a> and <a href="https://phpunit.de/">PHPUnit {PHPUnitVersion::id()}</a>';
        return Php_Unit_Version::series() < 10 ? new Html_Facade_Report($this->settings['low_limit'], $this->settings['high_limit'], $generator) : new Html_Facade_Report($generator, null, Thresholds::from($this->settings['low_limit'], $this->settings['high_limit']));
    }
    private function create_text_writer(): Text_Report
    {
        return Php_Unit_Version::series() < 10 ? new Text_Report($this->settings['low_limit'], $this->settings['high_limit'], $this->settings['show_uncovered'], $this->settings['show_only_summary']) : new Text_Report(Thresholds::from($this->settings['low_limit'], $this->settings['high_limit']), $this->settings['show_uncovered'], $this->settings['show_only_summary']);
    }
}
<?php

declare (strict_types=1);
namespace Codeception\Command;

use Codeception\Configuration;
use Codeception\Event\Suite_Event;
use Codeception\Event\Test_Event;
use Codeception\Events;
use Codeception\Lib\Generator\Actions;
use Codeception\Lib\Module_Container;
use Codeception\Stub;
use Codeception\Subscriber\Bootstrap as BootstrapLoader;
use Codeception\Subscriber\Console as ConsolePrinter;
use Codeception\Suite_Manager;
use Codeception\Test\Interfaces\Scenario_Driven;
use Codeception\Test\Test;
use Exception;
use function ini_set;
use InvalidArgumentException;
use function preg_match;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;
use function str_replace;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Event_Dispatcher\Event_Dispatcher;
/**
 * Shows step-by-step execution process for scenario driven tests without actually running them.
 *
 * * `codecept dry-run Acceptance`
 * * `codecept dry-run Acceptance MyCest`
 * * `codecept dry-run Acceptance checkout.feature`
 * * `codecept dry-run tests/Acceptance/MyCest.php`
 *
 */
#[As_Command(name: 'dry-run', description: 'Prints step-by-step scenario-driven test or a feature')]
class Dry_Run extends Command
{
    use Shared\Config_Trait;
    use Shared\Style_Trait;
    protected function configure(): void
    {
        $this->set_definition([new Input_Argument('suite', Input_Argument::REQUIRED, 'suite to scan for feature files'), new Input_Argument('test', Input_Argument::OPTIONAL, 'tests to be loaded')]);
        parent::configure();
    }
    public function get_description(): string
    {
        return 'Prints step-by-step scenario-driven test or a feature';
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $this->add_styles($output);
        $suite = (string) $input->get_argument('suite');
        $test = $input->get_argument('test');
        $config = $this->get_global_config();
        ini_set('memory_limit', $config['settings']['memory_limit'] ?? '1024M');
        if (!Configuration::is_empty() && !$test && str_starts_with($suite, (string) $config['paths']['tests'])) {
            [, $suite, $test] = $this->match_test_from_filename($suite, $config['paths']['tests']);
        }
        $settings = $this->get_suite_config($suite);
        $event_dispatcher = new Event_Dispatcher();
        $event_dispatcher->add_subscriber(new Console_Printer(['colors' => !$input->has_parameter_option('--no-ansi') xor $input->has_parameter_option('ansi'), 'steps' => true, 'verbosity' => Output_Interface::VERBOSITY_VERBOSE]));
        $event_dispatcher->add_subscriber(new Bootstrap_Loader());
        $suite_manager = new Suite_Manager($event_dispatcher, $suite, $settings, []);
        $module_container = $suite_manager->get_module_container();
        foreach (Configuration::modules($settings) as $module) {
            $this->mock_module($module, $module_container);
        }
        $suite_manager->load_tests($test);
        $tests = $suite_manager->get_suite()->get_tests();
        $event_dispatcher->dispatch(new Suite_Event($suite_manager->get_suite(), $settings), Events::SUITE_INIT);
        $event_dispatcher->dispatch(new Suite_Event($suite_manager->get_suite(), $settings), Events::SUITE_BEFORE);
        foreach ($tests as $test) {
            if ($test instanceof Test && $test instanceof Scenario_Driven) {
                $this->dry_run_test($output, $event_dispatcher, $test);
            }
        }
        $event_dispatcher->dispatch(new Suite_Event($suite_manager->get_suite()), Events::SUITE_AFTER);
        return 0;
    }
    protected function match_test_from_filename($filename, $tests_path): array
    {
        $filename = str_replace(['//', '\/', '\\'], '/', $filename);
        $res = preg_match("#^{$tests_path}/(.*?)/(.*)\$#", $filename, $matches);
        if (!$res) {
            throw new InvalidArgumentException("Test file can't be matched");
        }
        return $matches;
    }
    protected function dry_run_test(Output_Interface $output, Event_Dispatcher $event_dispatcher, Test $test): void
    {
        $event_dispatcher->dispatch(new Test_Event($test), Events::TEST_START);
        $event_dispatcher->dispatch(new Test_Event($test), Events::TEST_BEFORE);
        try {
            $test->test();
        } catch (Exception) {
        }
        $event_dispatcher->dispatch(new Test_Event($test), Events::TEST_AFTER);
        $event_dispatcher->dispatch(new Test_Event($test), Events::TEST_END);
        if ($test->get_metadata()->is_blocked()) {
            $output->writeln('');
            if ($skip = $test->get_metadata()->get_skip()) {
                $output->writeln('<warning> SKIPPED </warning>' . $skip);
            }
            if ($incomplete = $test->get_metadata()->get_incomplete()) {
                $output->writeln('<warning> INCOMPLETE </warning>' . $incomplete);
            }
        }
        $output->writeln('');
    }
    private function mock_module(string $module_name, Module_Container $module_container): void
    {
        $module = $module_container->get_module($module_name);
        $class = new ReflectionClass($module);
        $method_results = [];
        foreach ($class->get_methods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->is_constructor()) {
                continue;
            }
            $method_results[$method->get_name()] = $this->get_default_result_for_method($class, $method);
        }
        $module_container->mock($module_name, Stub::make_empty($module, $method_results));
    }
    private function get_default_result_for_method(ReflectionClass $class, ReflectionMethod $method): mixed
    {
        $return_type = $method->get_return_type();
        if ($return_type === null || $return_type->allows_null()) {
            return null;
        }
        if ($return_type instanceof ReflectionUnionType) {
            return $this->get_default_value_of_union_type($return_type);
        }
        if ($return_type instanceof ReflectionIntersectionType) {
            return $this->return_default_value_for_intersection_type($return_type);
        }
        if ($return_type->is_builtin()) {
            return $this->get_default_value_for_builtin_type($return_type);
        }
        $type_name = Actions::stringify_named_type($return_type, $class);
        return Stub::make_empty($type_name);
    }
    private function get_default_value_for_builtin_type(ReflectionNamedType $return_type): mixed
    {
        return match ($return_type->get_name()) {
            'mixed', 'never', 'void' => null,
            'string' => '',
            'int' => 0,
            'float' => 0.0,
            'bool' => false,
            'array', 'iterable' => [],
            'resource' => fopen('data://text/plain;base64,', 'r'),
            default => throw new Exception('Unsupported return type ' . $return_type->get_name()),
        };
    }
    private function get_default_value_of_union_type(ReflectionUnionType $return_type): mixed
    {
        $union_types = $return_type->get_types();
        foreach ($union_types as $type) {
            if ($type->is_builtin()) {
                return $this->get_default_value_for_builtin_type($type);
            }
        }
        return Stub::make_empty($union_types[0]);
    }
    private function return_default_value_for_intersection_type(ReflectionIntersectionType $return_type): mixed
    {
        $extends = null;
        $implements = [];
        foreach ($return_type->get_types() as $type) {
            if (class_exists($type->get_name())) {
                $extends = $type;
            } else {
                $implements[] = $type;
            }
        }
        $class_name = uniqid('anonymous_class_');
        $code = "abstract class {$class_name}";
        if ($extends !== null) {
            $code .= " extends \\{$extends}";
        }
        if ($implements !== []) {
            $code .= ' implements ' . implode(', ', $implements);
        }
        $code .= ' {}';
        eval($code);
        return Stub::make_empty($class_name);
    }
}
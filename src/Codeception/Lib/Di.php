<?php

declare (strict_types=1);
namespace Codeception\Lib;

use Codeception\Exception\Injection_Exception;
use Codeception\Util\Reflection_Helper;
use Exception;
use ReflectionClass;
use Reflection_Exception;
use ReflectionMethod;
use Reflection_Object;
use Throwable;
class Di
{
    /**
     * @var string
     */
    public const DEFAULT_INJECT_METHOD_NAME = '_inject';
    /**
     * @var object[]
     */
    protected array $container = [];
    public function __construct(protected ?Di $fallback = null)
    {
    }
    public function get(string $class_name): ?object
    {
        $class_name = ltrim($class_name, '\\');
        return $this->container[$class_name] ?? null;
    }
    public function set(object $class): void
    {
        $this->container[$class::class] = $class;
    }
    /**
     * @param string $injectMethodName Method which will be invoked after object creation;
     *                                 Resolved dependencies will be passed to it as arguments
     * @throws InjectionException|ReflectionException
     */
    public function instantiate(string $class_name, ?array $constructor_args = null, string $inject_method_name = self::DEFAULT_INJECT_METHOD_NAME): ?object
    {
        $class_name = ltrim($class_name, '\\');
        if (isset($this->container[$class_name])) {
            if ($this->container[$class_name] instanceof $class_name) {
                return $this->container[$class_name];
            }
            throw new Injection_Exception("Failed to resolve cyclic dependencies for class '{$class_name}'");
        }
        if ($this->fallback instanceof Di && $class = $this->fallback->get($class_name)) {
            return $class;
        }
        $this->container[$class_name] = false;
        try {
            $reflected_class = new ReflectionClass($class_name);
        } catch (Reflection_Exception $e) {
            throw new Injection_Exception("Failed to create instance of '{$class_name}'. " . $e->get_message());
        }
        if (!$reflected_class->is_instantiable()) {
            return null;
        }
        $constructor_args ??= $this->prepare_args($reflected_class->get_constructor());
        try {
            $object = $reflected_class->new_instance_args($constructor_args);
        } catch (Reflection_Exception $e) {
            throw new Injection_Exception("Failed to create instance of '{$class_name}'. " . $e->get_message());
        }
        $this->inject_dependencies($object, $inject_method_name);
        $this->container[$class_name] = $object;
        return $object;
    }
    /**
     * @param string $injectMethodName Method which will be invoked with resolved dependencies as its arguments
     * @throws InjectionException|ReflectionException
     */
    public function inject_dependencies(object $object, string $inject_method_name = self::DEFAULT_INJECT_METHOD_NAME, array $defaults = []): void
    {
        $reflected_object = new Reflection_Object($object);
        if ($reflected_object->has_method($inject_method_name)) {
            $reflected_method = $reflected_object->get_method($inject_method_name);
            try {
                $args = $this->prepare_args($reflected_method, $defaults);
            } catch (Exception $e) {
                $msg = $e->get_message();
                if ($e->get_previous() instanceof Throwable) {
                    $msg .= '; ' . $e->get_previous();
                }
                throw new Injection_Exception("Failed to inject dependencies in instance of '{$reflected_object->name}'. {$msg}");
            }
            $reflected_method->invoke_args($object, $args);
        }
    }
    protected function prepare_args(?ReflectionMethod $method = null, array $defaults = []): array
    {
        $args = [];
        if ($method instanceof ReflectionMethod) {
            foreach ($method->get_parameters() as $k => $parameter) {
                $dependency = Reflection_Helper::get_class_from_parameter($parameter);
                if (is_null($dependency)) {
                    if ($parameter->is_variadic()) {
                        continue;
                    }
                    if (!$parameter->is_optional()) {
                        $args[] = $defaults[$k] ?? throw new Injection_Exception("Parameter '{$parameter->name}' must have default value.");
                    } else {
                        $args[] = $parameter->get_default_value();
                    }
                } else {
                    try {
                        $arg = $this->instantiate($dependency);
                    } catch (Reflection_Exception $e) {
                        throw new Injection_Exception("Failed to resolve dependency '{$dependency}'. " . $e->get_message());
                    }
                    if (is_null($arg) && !$parameter->is_variadic()) {
                        throw new Injection_Exception("Failed to resolve dependency '{$dependency}'.");
                    }
                    $args[] = $arg;
                }
            }
        }
        return $args;
    }
}
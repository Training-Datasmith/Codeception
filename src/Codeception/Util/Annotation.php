<?php

declare (strict_types=1);
namespace Codeception\Util;

use function in_array;
use function is_object;
use function json_decode;
use function preg_match_all;
use Reflection_Attribute;
use ReflectionClass;
use ReflectionMethod;
use Reflector;
use function sprintf;
use function trim;
/**
 * Simple annotation parser. Take only key-value annotations for methods or class.
 */
class Annotation
{
    /**
     * @var ReflectionClass[]
     */
    protected static array $reflected_classes = [];
    protected static string $regex = '/@%s(?:[ \t]*(.*?))?[ \t]*(?:\*\/)?\r?$/m';
    protected ReflectionClass $reflected_class;
    /**
     * @var ReflectionClass|ReflectionMethod
     */
    protected Reflector $current_reflected_item;
    /**
     * Grabs annotation values.
     *
     * Usage example:
     *
     * ``` php
     * <?php
     * Annotation::forClass('MyTestCase')->fetch('guy');
     * Annotation::forClass('MyTestCase')->method('testData')->fetch('depends');
     * Annotation::forClass('MyTestCase')->method('testData')->fetchAll('depends');
     * ```
     */
    public static function for_class(object|string $class): self
    {
        $class_name = is_object($class) ? $class::class : $class;
        static::$reflected_classes[$class_name] ??= new ReflectionClass($class_name);
        return new self(static::$reflected_classes[$class_name]);
    }
    public static function for_method(object|string $class, string $method): self
    {
        return self::for_class($class)->method($method);
    }
    /**
     * Parses raw comment for annotations
     */
    public static function fetch_annotations_from_docblock(string $annotation, string $docblock): array
    {
        return preg_match_all(sprintf(self::$regex, $annotation), $docblock, $m) ? $m[1] : [];
    }
    /**
     * Fetches all available annotations
     */
    public static function fetch_all_annotations_from_docblock(string $docblock): array
    {
        if (!preg_match_all(sprintf(self::$regex, '(\w+)'), $docblock, $matched)) {
            return [];
        }
        $annotations = [];
        foreach ($matched[1] as $i => $annotation) {
            $annotations[$annotation][] = $matched[2][$i] ?? '';
        }
        return $annotations;
    }
    public function __construct(ReflectionClass $reflection_class)
    {
        $this->current_reflected_item = $this->reflected_class = $reflection_class;
    }
    public function method(string $method): self
    {
        $this->current_reflected_item = $this->reflected_class->get_method($method);
        return $this;
    }
    public function fetch(string $annotation): ?string
    {
        if (($attr = $this->attribute($annotation)) instanceof Reflection_Attribute) {
            return $attr->get_arguments()[0] ?? '';
        }
        $matches = self::fetch_annotations_from_docblock($annotation, (string) $this->current_reflected_item->get_doc_comment());
        return $matches[0] ?? null;
    }
    public function fetch_all(string $annotation): array
    {
        if (($attr = $this->attribute($annotation)) instanceof Reflection_Attribute) {
            if (!$attr->is_repeated()) {
                return $attr->get_arguments();
            }
            if ($annotation === 'example') {
                $annotation = 'examples';
            }
            $attr_class = 'Codeception\Attribute\\' . ucfirst($annotation);
            $attrs = array_filter($this->attributes(), static fn($a): bool => $a->get_name() === $attr_class);
            return $annotation === 'examples' ? array_map(static fn($a) => $a->get_arguments(), $attrs) : array_merge(...array_map(static fn($a) => $a->get_arguments(), $attrs));
        }
        return self::fetch_annotations_from_docblock($annotation, (string) $this->current_reflected_item->get_doc_comment());
    }
    public function attributes(): array
    {
        return array_filter($this->current_reflected_item->get_attributes(), static fn(Reflection_Attribute $a): bool => str_starts_with($a->get_name(), 'Codeception\Attribute\\'));
    }
    public function attribute(string $name): ?Reflection_Attribute
    {
        $search = 'Codeception\Attribute\\' . ucfirst($name === 'example' ? 'examples' : $name);
        foreach ($this->attributes() as $attr) {
            if ($attr->get_name() === $search) {
                return $attr;
            }
        }
        return null;
    }
    public function raw(): string|false
    {
        return $this->current_reflected_item->get_doc_comment();
    }
    /**
     * Returns an associative array value of annotation
     * Either JSON or Doctrine-annotation style allowed
     * Returns null if not a valid array data
     */
    public static function array_value(string $annotation): ?array
    {
        $annotation = trim($annotation);
        $first = $annotation[0] ?? '';
        if (in_array($first, ['{', '['])) {
            return json_decode($annotation, true, 512, JSON_THROW_ON_ERROR);
        }
        if ($first === '(') {
            preg_match_all('#(\w+)\s*=\s*"(.*?)"\s*[,)]#', $annotation, $matches, PREG_SET_ORDER);
            $data = [];
            foreach ($matches as $item) {
                $data[$item[1]] = $item[2];
            }
            return $data;
        }
        return null;
    }
}
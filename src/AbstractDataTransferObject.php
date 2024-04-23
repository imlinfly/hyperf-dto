<?php

/**
 * Created by PhpStorm.
 * User: LinFei
 * Created time 2023/12/16 16:10:40
 * E-mail: fly@eyabc.cn
 */
declare (strict_types=1);

namespace Lynnfly\HyperfDto;

use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ContainerInterface;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;

abstract class AbstractDataTransferObject
{
    /**
     * 属性列表
     * @var array
     */
    private static array $_properties = [];

    /**
     * 驼峰转下划线缓存
     * @var array
     */
    private static array $_underScoreCache = [];

    /**
     * 实例化时是否将属性名转为下划线
     * @var bool
     */
    protected bool $_toUnderScore = true;

    /**
     * 序列化时是否将属性名转为下划线
     */
    protected bool $_toUnderScoreOnSerialize = true;

    /**
     * 忽略赋值的属性
     * @var array
     */
    protected array $_ignore = [];

    /**
     * 构建Dto对象
     * @param array $data
     * @return static
     */
    public static function make(array $data = []): static
    {
        /** @var ContainerInterface $container */
        $container = ApplicationContext::getContainer();
        /** @var static $object */
        $object = clone $container->make(static::class);
        $object->fill($data);
        return $object;
    }

    /**
     * 填充属性
     * @param array $data
     * @return static
     */
    protected function fill(array $data): static
    {
        if (empty($data)) {
            return $this;
        }

        foreach ($this->getProperties() as $property) {
            /** @var null|ReflectionType|ReflectionNamedType $type */
            [$propertyName, $type] = $property;

            // 忽略赋值的属性
            if (in_array($propertyName, $this->_ignore)) {
                continue;
            }

            $paramName = $this->_toUnderScore ? $this->toUnderScore($propertyName) : $propertyName;

            if ($this->_toUnderScore && isset($data[$paramName])) {
                $value = $data[$paramName];
            } elseif (isset($data[$propertyName])) {
                $value = $data[$propertyName];
            } else {
                continue;
            }

            // 子类转换
            if ($type && !$type->isBuiltin()) {
                $this->{$propertyName} = $this->buildObjectValue($type->getName(), $value);
                continue;
            }

            // 强类型
            if ($type instanceof ReflectionNamedType) {
                $value = static::cast($value, $type->getName());
            }

            $this->{$propertyName} = $value;
        }

        return $this;
    }

    /**
     * 将对象转换为数组
     * @param bool|null $toUnderScore 是否将驼峰转下划线
     * @param array|null $only 指定返回的属性
     * @return array
     */
    public function toArray(bool $toUnderScore = null, array $only = null): array
    {
        $data = [];
        $toUnderScore ??= $this->_toUnderScoreOnSerialize;

        // 获取初始化的属性
        foreach ($this->getProperties() as $property) {
            [$name] = $property;

            if (!isset($this->{$name})) {
                continue;
            }

            $value = $this->{$name};

            if ($toUnderScore) {
                $name = $this->toUnderScore($name);
            }

            $data[$name] = $value instanceof self ? $value->toArray($toUnderScore) : $value;
        }

        if (null !== $only) {
            return array_intersect_key($data, array_flip($only));
        }

        return $data;
    }

    /**
     * 转换为json
     * @param bool|null $toUnderScore 是否将驼峰转下划线
     * @param array|null $only 指定返回的属性
     * @param int $options json_encode options
     * @param int $depth json_encode depth
     * @return string
     */
    public function toJson(bool $toUnderScore = null, array $only = null, int $options = 0, int $depth = 512): string
    {
        return json_encode($this->toArray($toUnderScore, $only), $options, $depth);
    }

    /**
     * 转换为JSON
     * @return string
     */
    public function __toString(): string
    {
        return $this->toJson();
    }

    /**
     * 构建对象值
     * @param string $class
     * @param mixed $data
     * @return mixed
     */
    protected function buildObjectValue(string $class, mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        // 判断对象是否是当前类的子类
        if (!is_subclass_of($class, self::class)) {
            throw new InvalidArgumentException("{$class} is not subclass of " . self::class);
        }

        /** @var self $class */
        return $class::make($data);
    }

    /**
     * 驼峰转下划线
     * @param string $name
     * @return string
     */
    protected function toUnderScore(string $name): string
    {
        return self::$_underScoreCache[$name] ??=
            strtolower(preg_replace('/(?<=[a-z])([A-Z])/', '_$1', $name));
    }

    /**
     * 获取属性列表
     * @return array
     */
    protected function getProperties(): array
    {
        $store = &self::$_properties[static::class];
        if (isset($store)) {
            return $store;
        }

        $ref = new ReflectionClass($this);
        $properties = $ref->getProperties(ReflectionProperty::IS_PUBLIC);
        foreach ($properties as $property) {
            $store[] = [$property->getName(), $property->getType()];
        }

        return $store;
    }

    /**
     * 强类型转换
     * @param mixed $value
     * @param string $type
     * @return mixed
     */
    protected static function cast(mixed $value, string $type): mixed
    {
        return match ($type) {
            'int' => (int)$value,
            'float' => (float)$value,
            'bool' => (bool)$value,
            'string' => (string)$value,
            default => $value,
        };
    }
}

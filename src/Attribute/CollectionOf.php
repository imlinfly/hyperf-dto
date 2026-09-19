<?php

declare(strict_types=1);

namespace Lynnfly\HyperfDto\Attribute;

use Attribute;
use InvalidArgumentException;
use Lynnfly\HyperfDto\AbstractDataTransferObject;
use ReflectionClass;

/**
 * 声明 DTO 数组属性的元素 DTO 类型。
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class CollectionOf
{
    /**
     * @param class-string<AbstractDataTransferObject> $class
     */
    public function __construct(public string $class)
    {
        if (! is_a($class, AbstractDataTransferObject::class, true) || (new ReflectionClass($class))->isAbstract()) {
            throw new InvalidArgumentException("{$class} must extend " . AbstractDataTransferObject::class);
        }
    }
}

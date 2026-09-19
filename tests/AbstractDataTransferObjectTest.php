<?php

declare(strict_types=1);

namespace Lynnfly\HyperfDto\Tests;

use Hyperf\Context\ApplicationContext;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSource;
use InvalidArgumentException;
use Lynnfly\HyperfDto\AbstractDataTransferObject;
use Lynnfly\HyperfDto\Attribute\CollectionOf;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * 验证 DTO 集合元素 hydration、递归序列化和兼容行为。
 */
final class AbstractDataTransferObjectTest extends TestCase
{
    protected function setUp(): void
    {
        ApplicationContext::setContainer(new Container(new DefinitionSource([])));
    }

    public function testCollectionOfHydratesArraysAndPreservesKeys(): void
    {
        $dto = CollectionDto::make([
            'items' => [
                'first' => ['first_name' => '张三'],
                7 => ['first_name' => '李四'],
            ],
        ]);

        self::assertInstanceOf(ItemDto::class, $dto->items['first']);
        self::assertSame(['first', 7], array_keys($dto->items));
        self::assertSame('李四', $dto->items[7]->firstName);
    }

    public function testExistingDtoInstancesArePreserved(): void
    {
        $item = ItemDto::make(['first_name' => '张三']);
        $dto = CollectionDto::make(['items' => ['item' => $item]]);

        self::assertSame($item, $dto->items['item']);
    }

    public function testNestedCollectionOfHydratesRecursively(): void
    {
        $dto = NestedCollectionDto::make([
            'groups' => [
                ['items' => [['first_name' => '张三']]],
            ],
        ]);

        self::assertInstanceOf(GroupDto::class, $dto->groups[0]);
        self::assertInstanceOf(ItemDto::class, $dto->groups[0]->items[0]);
    }

    public function testNullableCollectionAcceptsNull(): void
    {
        $dto = NullableCollectionDto::make(['items' => null]);

        self::assertNull($dto->items);
    }

    public function testCollectionOfRejectsNonArrayPropertyValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Property 'items' must be an array");

        CollectionDto::make(['items' => 'invalid']);
    }

    /**
     * @dataProvider invalidCollectionItemProvider
     */
    public function testCollectionOfRejectsInvalidElements(mixed $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Property 'items' item '0'");

        CollectionDto::make(['items' => [$value]]);
    }

    public static function invalidCollectionItemProvider(): array
    {
        return [[null], ['invalid'], [new stdClass()]];
    }

    public function testCollectionOfRejectsNonDtoElementClass(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CollectionOf(stdClass::class);
    }

    public function testToArrayRecursivelySerializesDtoCollections(): void
    {
        $dto = NestedCollectionDto::make([
            'groups' => [
                'writers' => ['items' => [['first_name' => '张三']]],
            ],
        ]);

        self::assertSame([
            'groups' => [
                'writers' => [
                    'items' => [
                        ['first_name' => '张三'],
                    ],
                ],
            ],
        ], $dto->toArray());
    }

    public function testUnannotatedArraysKeepRawArrayHydration(): void
    {
        $dto = PlainArrayDto::make(['values' => [['value' => 1]]]);

        self::assertSame([['value' => 1]], $dto->values);
    }

    public function testOrdinaryNestedDtoHydrationStillWorks(): void
    {
        $dto = NestedDto::make(['item' => ['first_name' => '张三']]);

        self::assertInstanceOf(ItemDto::class, $dto->item);
    }
}

/**
 * 提供集合元素 DTO 测试边界。
 */
final class ItemDto extends AbstractDataTransferObject
{
    public string $firstName;
}

/**
 * 提供单层 DTO 集合测试边界。
 */
final class CollectionDto extends AbstractDataTransferObject
{
    #[CollectionOf(ItemDto::class)]
    public array $items;
}

/**
 * 提供嵌套 DTO 集合测试边界。
 */
final class NestedCollectionDto extends AbstractDataTransferObject
{
    #[CollectionOf(GroupDto::class)]
    public array $groups;
}

/**
 * 提供集合元素包含集合的测试边界。
 */
final class GroupDto extends AbstractDataTransferObject
{
    #[CollectionOf(ItemDto::class)]
    public array $items;
}

/**
 * 提供可空集合属性测试边界。
 */
final class NullableCollectionDto extends AbstractDataTransferObject
{
    #[CollectionOf(ItemDto::class)]
    public ?array $items;
}

/**
 * 提供普通嵌套 DTO 属性测试边界。
 */
final class NestedDto extends AbstractDataTransferObject
{
    public ItemDto $item;
}

/**
 * 提供无集合元数据数组的兼容测试边界。
 */
final class PlainArrayDto extends AbstractDataTransferObject
{
    public array $values;
}

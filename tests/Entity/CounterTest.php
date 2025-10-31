<?php

namespace CounterBundle\Tests\Entity;

use CounterBundle\Entity\Counter;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * Counter实体的单元测试
 *
 * @internal
 */
#[CoversClass(Counter::class)]
final class CounterTest extends AbstractEntityTestCase
{
    protected function createEntity(): Counter
    {
        return new Counter();
    }

    /**
     * 提供 Counter 实体属性及其样本值的 Data Provider.
     * @return iterable<array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield ['name', 'test-counter'];
        yield ['count', 42];
        yield ['context', ['key' => 'value']];
        yield ['createTime', new \DateTimeImmutable()];
        yield ['updateTime', new \DateTimeImmutable()];
    }

    /**
     * 测试创建计数器并设置基本属性
     */
    public function testCreateCounter(): void
    {
        $counter = new Counter();

        // 测试初始状态
        $this->assertNull($counter->getName());
        $this->assertEquals(0, $counter->getCount());
        $this->assertNull($counter->getContext());
        $this->assertNull($counter->getCreateTime());
        $this->assertNull($counter->getUpdateTime());

        // 测试设置和获取名称
        $counter->setName('test-counter');
        $this->assertEquals('test-counter', $counter->getName());

        // 测试设置和获取计数
        $counter->setCount(10);
        $this->assertEquals(10, $counter->getCount());

        // 测试设置和获取上下文
        $context = ['key' => 'value'];
        $counter->setContext($context);
        $this->assertEquals($context, $counter->getContext());

        // 测试设置和获取创建时间
        $createTime = new \DateTimeImmutable();
        $counter->setCreateTime($createTime);
        $this->assertSame($createTime, $counter->getCreateTime());

        // 测试设置和获取更新时间
        $updateTime = new \DateTimeImmutable();
        $counter->setUpdateTime($updateTime);
        $this->assertSame($updateTime, $counter->getUpdateTime());
    }

    /**
     * 测试方法链式调用
     */
    public function testMethodChaining(): void
    {
        $counter = new Counter();

        // 测试设置方法（setter方法现在返回void以符合静态分析要求）
        $counter->setName('chain-test');
        $counter->setCount(5);
        $counter->setContext(['chain' => true]);

        $this->assertEquals('chain-test', $counter->getName());
        $this->assertEquals(5, $counter->getCount());
        $this->assertEquals(['chain' => true], $counter->getContext());
    }
}

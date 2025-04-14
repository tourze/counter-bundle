<?php

namespace CounterBundle\Tests\Entity;

use CounterBundle\Entity\Counter;
use PHPUnit\Framework\TestCase;

/**
 * Counter实体的单元测试
 */
class CounterTest extends TestCase
{
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
        $createTime = new \DateTime();
        $counter->setCreateTime($createTime);
        $this->assertSame($createTime, $counter->getCreateTime());

        // 测试设置和获取更新时间
        $updateTime = new \DateTime();
        $counter->setUpdateTime($updateTime);
        $this->assertSame($updateTime, $counter->getUpdateTime());
    }

    /**
     * 测试方法链式调用
     */
    public function testMethodChaining(): void
    {
        $counter = new Counter();

        // 测试链式调用
        $result = $counter
            ->setName('chain-test')
            ->setCount(5)
            ->setContext(['chain' => true]);

        $this->assertInstanceOf(Counter::class, $result);
        $this->assertEquals('chain-test', $counter->getName());
        $this->assertEquals(5, $counter->getCount());
        $this->assertEquals(['chain' => true], $counter->getContext());
    }
}

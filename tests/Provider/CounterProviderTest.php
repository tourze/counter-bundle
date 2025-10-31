<?php

namespace CounterBundle\Tests\Provider;

use CounterBundle\Entity\Counter;
use CounterBundle\Provider\CounterProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * 测试 CounterProvider 接口的实现
 *
 * @internal
 */
#[CoversClass(CounterProvider::class)]
#[RunTestsInSeparateProcesses]
final class CounterProviderTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 这个测试不需要特殊的设置
    }

    /**
     * 测试简单的 CounterProvider 实现
     */
    public function testSimpleCounterProvider(): void
    {
        // 创建匿名实现类
        $provider = new class implements CounterProvider {
            /**
             * @return iterable<Counter>
             */
            public function getCounters(): iterable
            {
                $counter1 = new Counter();
                $counter1->setName('test-counter-1');
                $counter1->setCount(5);

                $counter2 = new Counter();
                $counter2->setName('test-counter-2');
                $counter2->setCount(10);

                return [$counter1, $counter2];
            }
        };

        // 测试 getCounters 方法
        $counters = iterator_to_array($provider->getCounters());

        $this->assertCount(2, $counters);
        $this->assertEquals('test-counter-1', $counters[0]->getName());
        $this->assertEquals(5, $counters[0]->getCount());
        $this->assertEquals('test-counter-2', $counters[1]->getName());
        $this->assertEquals(10, $counters[1]->getCount());
    }

    /**
     * 测试迭代器作为返回值
     */
    public function testIterableCounterProvider(): void
    {
        // 创建使用生成器的匿名实现类
        $provider = new class implements CounterProvider {
            /**
             * @return iterable<Counter>
             */
            public function getCounters(): iterable
            {
                for ($i = 1; $i <= 3; ++$i) {
                    $counter = new Counter();
                    $counter->setName('counter-' . $i);
                    $counter->setCount($i * 10);
                    yield $counter;
                }
            }
        };

        // 测试通过生成器返回的计数器
        $counters = iterator_to_array($provider->getCounters());

        $this->assertCount(3, $counters);
        $this->assertEquals('counter-1', $counters[0]->getName());
        $this->assertEquals(10, $counters[0]->getCount());
        $this->assertEquals('counter-2', $counters[1]->getName());
        $this->assertEquals(20, $counters[1]->getCount());
        $this->assertEquals('counter-3', $counters[2]->getName());
        $this->assertEquals(30, $counters[2]->getCount());
    }
}

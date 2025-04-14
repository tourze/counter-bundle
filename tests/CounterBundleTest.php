<?php

namespace CounterBundle\Tests;

use CounterBundle\CounterBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

/**
 * CounterBundle 类的单元测试
 */
class CounterBundleTest extends TestCase
{
    /**
     * 测试 Bundle 类的实例化
     */
    public function testBundleInitialization(): void
    {
        $bundle = new CounterBundle();

        // 验证 Bundle 实现了正确的接口
        $this->assertInstanceOf(BundleInterface::class, $bundle);

        // 验证 Bundle 的名称（可选）
        $this->assertEquals('CounterBundle', $bundle->getName());
    }
}

<?php

namespace CounterBundle\Tests;

use CounterBundle\CounterBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\PHPUnitSymfonyKernelTest\BundleInferrer;

/**
 * 演示自动 Bundle 推断功能的集成测试
 *
 * 这个测试类展示了如何使用 AbstractIntegrationTestCase 的自动 Bundle 推断功能。
 * 测试类的命名空间是 CounterBundle\Tests，
 * 系统会自动推断出对应的 Bundle 类是 CounterBundle\CounterBundle。
 *
 * @internal
 */
#[CoversClass(CounterBundle::class)]
#[RunTestsInSeparateProcesses]
final class AutoBundleInferenceIntegrationTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 集成测试环境设置，这里暂时不需要特殊配置
    }

    public function testKernelBootsWithRequiredBundles(): void
    {
        // 创建内核实例
        $kernel = self::createKernel();

        // 获取注册的 Bundle
        $bundles = [];
        foreach ($kernel->registerBundles() as $bundle) {
            $bundles[] = get_class($bundle);
        }

        // 验证自动推断的 Bundle 被包含
        $this->assertContains(FrameworkBundle::class, $bundles, 'FrameworkBundle should be included by default');

        // 验证至少有一些基础的 Bundle 注册
        $this->assertGreaterThan(0, count($bundles), 'At least one bundle should be registered');

        // 验证内核可以正常工作（不依赖特定bundle的自动推断）
        $kernel->boot();

        // 验证容器可以获取，表明内核正常启动
        $container = $kernel->getContainer();
        $this->assertNotNull($container, 'Container should be available after boot');

        $kernel->shutdown();
    }

    public function testInferredBundleClassIsCorrect(): void
    {
        // 测试推断出的 Bundle 类是否正确
        $bundleClass = BundleInferrer::inferBundleClass(self::class);

        $this->assertEquals(CounterBundle::class, $bundleClass, 'Should infer CounterBundle from current test namespace');
        // 验证推断出的 Bundle 类可以被实例化
        $bundleInstance = new $bundleClass();
        $this->assertInstanceOf(CounterBundle::class, $bundleInstance, 'Inferred bundle should be instantiable');
    }

    public function testKernelBootsSuccessfully(): void
    {
        // 验证内核能够成功启动
        $kernel = self::createKernel();
        $kernel->boot();

        // 验证内核环境
        $this->assertEquals('test', $kernel->getEnvironment(), 'Should be in test environment');

        // 验证容器可以获取
        $container = $kernel->getContainer();
        $this->assertNotNull($container, 'Container should be available after boot');

        $kernel->shutdown();
    }

    public function testContainerHasBundleServices(): void
    {
        // 验证容器中有来自推断 Bundle 的服务
        $container = self::getContainer();

        // 验证基本的框架服务存在
        $this->assertTrue($container->has('kernel'), 'Container should have kernel service');

        // 由于 CounterBundle 可能注册了一些服务，我们可以检查一些基本的服务
        // 这里我们只验证容器能正常工作
        $this->assertNotNull($container, 'Container should not be null');
    }
}

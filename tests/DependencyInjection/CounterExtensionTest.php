<?php

namespace CounterBundle\Tests\DependencyInjection;

use CounterBundle\DependencyInjection\CounterExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;

/**
 * CounterExtension 的单元测试
 *
 * @internal
 */
#[CoversClass(CounterExtension::class)]
final class CounterExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
    private CounterExtension $extension;

    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->extension = new CounterExtension();
        $this->container = new ContainerBuilder();

        // 设置必要的参数以防止 AutoExtension 出错
        $this->container->setParameter('kernel.environment', 'test');
    }

    /**
     * 测试加载配置不抛出异常
     */
    public function testLoadWithValidConfigDoesNotThrowException(): void
    {
        // Arrange
        $config = [
            'counter' => [
                'enabled' => true,
            ],
        ];

        // Act & Assert
        $this->extension->load($config, $this->container);

        // 验证加载后容器中有服务定义
        $this->assertGreaterThan(0, count($this->container->getDefinitions()));

        // 验证没有抛出异常（能正常执行到这里）
        $this->assertInstanceOf(ContainerBuilder::class, $this->container);
    }

    /**
     * 测试扩展别名（如果有的话）
     */
    public function testGetAlias(): void
    {
        // 如果扩展有别名，可以测试
        // 当前 CounterExtension 没有重写 getAlias 方法，所以会使用默认的
        $expectedAlias = 'counter'; // 基于类名 CounterExtension
        $this->assertEquals($expectedAlias, $this->extension->getAlias());
    }

    /**
     * 测试扩展命名空间
     */
    public function testExtensionNamespace(): void
    {
        $reflection = new \ReflectionClass($this->extension);
        $this->assertEquals('CounterBundle\DependencyInjection', $reflection->getNamespaceName());
    }
}

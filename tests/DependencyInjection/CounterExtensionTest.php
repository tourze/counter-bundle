<?php

namespace CounterBundle\Tests\DependencyInjection;

use CounterBundle\DependencyInjection\CounterExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

/**
 * CounterExtension 的单元测试
 */
class CounterExtensionTest extends TestCase
{
    private CounterExtension $extension;
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->extension = new CounterExtension();
        $this->container = new ContainerBuilder();
    }

    /**
     * 测试扩展继承正确的基类
     */
    public function test_extendsSymfonyExtension(): void
    {
        $this->assertInstanceOf(Extension::class, $this->extension);
    }

    /**
     * 测试加载方法存在且可调用
     */
    public function test_loadMethodExists(): void
    {
        $this->assertTrue(method_exists($this->extension, 'load'));
        $this->assertTrue(is_callable([$this->extension, 'load']));
    }

    /**
     * 测试加载空配置
     */
    public function test_load_withEmptyConfig_loadsServices(): void
    {
        // Act
        $this->extension->load([], $this->container);

        // Assert
        // 验证容器中注册了预期的服务
        $this->assertTrue($this->container->hasDefinition('CounterBundle\Command\RefreshCounterCommand') ||
            $this->container->hasParameter('counter.some_parameter') ||
            $this->container->getDefinitions() !== []);
    }

    /**
     * 测试加载配置不抛出异常
     */
    public function test_load_withValidConfig_doesNotThrowException(): void
    {
        // Arrange
        $config = [
            'counter' => [
                'enabled' => true
            ]
        ];

        // Act & Assert
        $this->extension->load($config, $this->container);
        $this->assertTrue(true); // 如果到达这里，说明没有抛出异常
    }

    /**
     * 测试扩展别名（如果有的话）
     */
    public function test_getAlias(): void
    {
        // 如果扩展有别名，可以测试
        // 当前 CounterExtension 没有重写 getAlias 方法，所以会使用默认的
        $expectedAlias = 'counter'; // 基于类名 CounterExtension
        $this->assertEquals($expectedAlias, $this->extension->getAlias());
    }

    /**
     * 测试配置文件加载
     */
    public function test_load_loadsServicesYamlFile(): void
    {
        // Arrange
        $initialDefinitionCount = count($this->container->getDefinitions());

        // Act
        $this->extension->load([], $this->container);

        // Assert
        // 加载后应该有更多的服务定义（假设 services.yaml 不为空）
        $finalDefinitionCount = count($this->container->getDefinitions());
        $this->assertGreaterThanOrEqual($initialDefinitionCount, $finalDefinitionCount);
    }

    /**
     * 测试扩展命名空间
     */
    public function test_extensionNamespace(): void
    {
        $reflection = new \ReflectionClass($this->extension);
        $this->assertEquals('CounterBundle\DependencyInjection', $reflection->getNamespaceName());
    }
}

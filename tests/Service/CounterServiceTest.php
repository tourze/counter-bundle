<?php

namespace CounterBundle\Tests\Service;

use CounterBundle\Service\CounterService;
use PHPUnit\Framework\TestCase;

/**
 * CounterService 的单元测试
 */
class CounterServiceTest extends TestCase
{
    private CounterService $service;

    protected function setUp(): void
    {
        $this->service = new CounterService();
    }

    /**
     * 测试服务类的实例化
     */
    public function test_serviceCanBeInstantiated(): void
    {
        $this->assertInstanceOf(CounterService::class, $this->service);
    }

    /**
     * 测试服务类的基本存在性
     * 由于当前服务类为空，只验证其存在和可实例化
     */
    public function test_serviceExists(): void
    {
        $reflection = new \ReflectionClass(CounterService::class);
        $this->assertTrue($reflection->isInstantiable());
        $this->assertFalse($reflection->isAbstract());
        $this->assertFalse($reflection->isInterface());
    }
}

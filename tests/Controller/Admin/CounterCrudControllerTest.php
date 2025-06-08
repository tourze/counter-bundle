<?php

namespace CounterBundle\Tests\Controller\Admin;

use CounterBundle\Controller\Admin\CounterCrudController;
use CounterBundle\Entity\Counter;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * CounterCrudController 的单元测试
 */
class CounterCrudControllerTest extends TestCase
{
    private CounterCrudController $controller;

    protected function setUp(): void
    {
        $this->controller = new CounterCrudController();
    }

    /**
     * 测试控制器继承正确的基类
     */
    public function testExtendsAbstractCrudController(): void
    {
        $this->assertInstanceOf(AbstractCrudController::class, $this->controller);
    }

    /**
     * 测试获取实体FQCN
     */
    public function testGetEntityFqcn(): void
    {
        $this->assertEquals(Counter::class, CounterCrudController::getEntityFqcn());
    }

    /**
     * 测试配置CRUD方法
     */
    public function testConfigureCrud(): void
    {
        $crud = Crud::new();
        $result = $this->controller->configureCrud($crud);

        $this->assertInstanceOf(Crud::class, $result);
    }

    /**
     * 测试配置字段方法
     */
    public function testConfigureFields(): void
    {
        $fields = iterator_to_array($this->controller->configureFields(Crud::PAGE_INDEX));

        $this->assertNotEmpty($fields);
        
        // 验证包含基本字段
        $fieldNames = array_map(fn($field) => $field->getProperty(), $fields);
        $this->assertContains('id', $fieldNames);
        $this->assertContains('name', $fieldNames);
        $this->assertContains('count', $fieldNames);
        $this->assertContains('context', $fieldNames);
        $this->assertContains('createTime', $fieldNames);
        $this->assertContains('updateTime', $fieldNames);
    }

    /**
     * 测试是否有AdminCrud属性
     */
    public function testHasAdminCrudAttribute(): void
    {
        $reflection = new ReflectionClass($this->controller);
        $attributes = $reflection->getAttributes();

        $hasAdminCrudAttribute = false;
        foreach ($attributes as $attribute) {
            if (str_contains($attribute->getName(), 'AdminCrud')) {
                $hasAdminCrudAttribute = true;
                break;
            }
        }

        $this->assertTrue($hasAdminCrudAttribute, 'Controller should have AdminCrud attribute');
    }

    /**
     * 测试配置动作方法
     */
    public function testConfigureActions(): void
    {
        $actions = $this->controller->configureActions(
            \EasyCorp\Bundle\EasyAdminBundle\Config\Actions::new()
        );

        $this->assertInstanceOf(\EasyCorp\Bundle\EasyAdminBundle\Config\Actions::class, $actions);
    }

    /**
     * 测试配置过滤器方法
     */
    public function testConfigureFilters(): void
    {
        $filters = $this->controller->configureFilters(
            \EasyCorp\Bundle\EasyAdminBundle\Config\Filters::new()
        );

        $this->assertInstanceOf(\EasyCorp\Bundle\EasyAdminBundle\Config\Filters::class, $filters);
    }
} 
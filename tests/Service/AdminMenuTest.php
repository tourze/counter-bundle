<?php

namespace CounterBundle\Tests\Service;

use CounterBundle\Service\AdminMenu;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use PHPUnit\Framework\TestCase;

/**
 * AdminMenu 服务的单元测试
 */
class AdminMenuTest extends TestCase
{
    private AdminMenu $adminMenu;

    protected function setUp(): void
    {
        $this->adminMenu = new AdminMenu();
    }

    /**
     * 测试获取菜单项
     */
    public function testGetMenuItems(): void
    {
        $menuItems = $this->adminMenu->getMenuItems();

        $this->assertIsArray($menuItems);
        $this->assertNotEmpty($menuItems);

        // 验证所有项目都是 MenuItem 实例
        foreach ($menuItems as $item) {
            $this->assertInstanceOf(MenuItem::class, $item);
        }

        // 验证菜单项数量
        $this->assertGreaterThanOrEqual(2, count($menuItems), '应该包含至少2个菜单项');
    }

    /**
     * 测试菜单项的结构
     */
    public function testMenuItemsStructure(): void
    {
        $menuItems = $this->adminMenu->getMenuItems();

        // 验证至少有两个菜单项
        $this->assertCount(2, $menuItems);
        
        // 验证菜单项都是 MenuItem 实例
        $this->assertInstanceOf(MenuItem::class, $menuItems[0]);
        $this->assertInstanceOf(MenuItem::class, $menuItems[1]);
    }
} 
<?php

namespace CounterBundle\Tests\Service;

use CounterBundle\Service\AdminMenu;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Menu\MenuItemInterface;
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
        $this->assertNotEmpty($menuItems);

        // 验证所有项目都是 MenuItemInterface 实例
        foreach ($menuItems as $index => $item) {
            $this->assertInstanceOf(
                MenuItemInterface::class,
                $item,
                sprintf('Item %d is not a MenuItemInterface, it is %s', $index, get_class($item))
            );
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

        // 验证菜单项都是 MenuItemInterface 实例
        foreach ($menuItems as $index => $item) {
            $this->assertInstanceOf(
                MenuItemInterface::class,
                $item,
                sprintf('Item %d is not a MenuItemInterface, it is %s', $index, get_class($item))
            );
        }
    }
}

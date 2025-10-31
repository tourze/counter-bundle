<?php

namespace CounterBundle\Tests\Service;

use CounterBundle\Service\AdminMenu;
use Knp\Menu\ItemInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminMenuTestCase;

/**
 * AdminMenu 服务的集成测试
 *
 * @internal
 */
#[CoversClass(AdminMenu::class)]
#[RunTestsInSeparateProcesses]
final class AdminMenuTest extends AbstractEasyAdminMenuTestCase
{
    private AdminMenu $adminMenu;

    protected function onSetUp(): void
    {
        // 集成测试环境设置，这里暂时不需要特殊配置
    }

    private function getAdminMenuService(): AdminMenu
    {
        return self::getService(AdminMenu::class);
    }

    /**
     * 测试菜单提供者可以被调用
     */
    public function testGetMenuItems(): void
    {
        $this->adminMenu = $this->getAdminMenuService();

        // 创建mock的ItemInterface
        $rootItem = $this->createMock(ItemInterface::class);
        $statsItem = $this->createMock(ItemInterface::class);

        // 设置mock的期望行为
        $rootItem->expects($this->exactly(2))
            ->method('getChild')
            ->with('数据统计')
            ->willReturnOnConsecutiveCalls(null, $statsItem)
        ;

        $rootItem->expects($this->once())
            ->method('addChild')
            ->with('数据统计')
            ->willReturn($statsItem)
        ;

        // 创建一个新的 mock 子菜单项，用于 addChild 返回
        $counterItem = $this->createMock(ItemInterface::class);

        $statsItem->expects($this->once())
            ->method('addChild')
            ->with('计数器管理')
            ->willReturn($counterItem)
        ;

        $counterItem->expects($this->once())
            ->method('setUri')
            ->willReturn($counterItem)
        ;

        $counterItem->expects($this->once())
            ->method('setAttribute')
            ->with('icon', 'fas fa-calculator')
            ->willReturn($counterItem)
        ;

        // 调用__invoke方法
        ($this->adminMenu)($rootItem);
    }

    /**
     * 测试菜单项的结构
     */
    public function testMenuItemsStructure(): void
    {
        $this->adminMenu = $this->getAdminMenuService();

        // 创建mock的ItemInterface
        /** @var MockObject&ItemInterface $rootItem */
        $rootItem = $this->createMock(ItemInterface::class);
        /** @var MockObject&ItemInterface $statsItem */
        $statsItem = $this->createMock(ItemInterface::class);

        // 验证创建数据统计父菜单
        $rootItem->expects($this->exactly(2))
            ->method('getChild')
            ->with('数据统计')
            ->willReturnOnConsecutiveCalls(null, $statsItem)
        ;

        $rootItem->expects($this->once())
            ->method('addChild')
            ->with('数据统计')
            ->willReturn($statsItem)
        ;

        // 验证添加计数器管理子菜单
        $statsItem->expects($this->once())
            ->method('addChild')
            ->with('计数器管理')
        ;

        // 调用菜单提供者
        ($this->adminMenu)($rootItem);
    }
}

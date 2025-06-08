<?php

namespace CounterBundle\Service;

use CounterBundle\Controller\Admin\CounterCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;

/**
 * 计数器模块的管理菜单
 */
class AdminMenu
{
    /**
     * 获取计数器模块的菜单项
     */
    public function getMenuItems(): array
    {
        return [
            MenuItem::section('计数器管理', 'fa fa-calculator')->setPermission('ROLE_ADMIN'),
            MenuItem::linkToCrud('计数器列表', 'fa fa-list', CounterCrudController::class)
                ->setPermission('ROLE_ADMIN'),
        ];
    }
} 
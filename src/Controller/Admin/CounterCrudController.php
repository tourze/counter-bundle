<?php

namespace CounterBundle\Controller\Admin;

use CounterBundle\Entity\Counter;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

#[AdminCrud(routePath: '/counter/counter', routeName: 'counter_counter')]
class CounterCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Counter::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('计数器')
            ->setEntityLabelInPlural('计数器管理')
            ->setPageTitle('index', '计数器列表')
            ->setPageTitle('new', '创建计数器')
            ->setPageTitle('edit', '编辑计数器')
            ->setPageTitle('detail', '计数器详情')
            ->setHelp('index', '计数器用于统计各种实体的数量，支持自动更新和手动调整')
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['id', 'name']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->setMaxLength(9999)
            ->hideOnForm();

        yield TextField::new('name', '计数器名称')
            ->setHelp('计数器的唯一标识符，格式通常为：实体类名::total');

        yield IntegerField::new('count', '计数值')
            ->setHelp('当前计数值，可手动调整');

        yield ArrayField::new('context', '上下文信息')
            ->hideOnIndex()
            ->setHelp('存储与计数器相关的额外信息');

        yield DateTimeField::new('createTime', '创建时间')
            ->hideOnForm()
            ->setFormat('yyyy-MM-dd HH:mm:ss');

        yield DateTimeField::new('updateTime', '更新时间')
            ->hideOnForm()
            ->setFormat('yyyy-MM-dd HH:mm:ss');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, Action::EDIT)
            ->add(Crud::PAGE_INDEX, Action::DELETE)
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, Action::EDIT, Action::DELETE]);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('name', '计数器名称'))
            ->add(NumericFilter::new('count', '计数值'))
            ->add(DateTimeFilter::new('createTime', '创建时间'))
            ->add(DateTimeFilter::new('updateTime', '更新时间'));
    }
}

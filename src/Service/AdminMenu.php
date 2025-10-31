<?php

namespace CounterBundle\Service;

use CounterBundle\Entity\Counter;
use Knp\Menu\ItemInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;
use Tourze\EasyAdminMenuBundle\Service\MenuProviderInterface;

/**
 * 计数器模块的管理菜单
 */
#[Autoconfigure(public: true)]
readonly class AdminMenu implements MenuProviderInterface
{
    public function __construct(
        private LinkGeneratorInterface $linkGenerator,
    ) {
    }

    public function __invoke(ItemInterface $item): void
    {
        if (null === $item->getChild('数据统计')) {
            $item->addChild('数据统计');
        }

        $statsMenu = $item->getChild('数据统计');
        if (null === $statsMenu) {
            return;
        }

        $statsMenu->addChild('计数器管理')
            ->setUri($this->linkGenerator->getCurdListPage(Counter::class))
            ->setAttribute('icon', 'fas fa-calculator')
        ;
    }
}

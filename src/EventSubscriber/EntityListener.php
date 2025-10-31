<?php

namespace CounterBundle\EventSubscriber;

use CounterBundle\Provider\EntityTotalCountProvider;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Events;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Service\ResetInterface;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postRemove)]
#[AutoconfigureTag(name: 'as-coroutine')]
#[WithMonologChannel(channel: 'counter')]
class EntityListener implements ResetInterface
{
    public function __construct(
        private readonly EntityTotalCountProvider $countProvider,
        private readonly LoggerInterface $logger,
    ) {
    }

    public const FORMAT = '%s::total';

    /**
     * @var array<string>
     */
    private array $increaseList = [];

    /**
     * @var array<string>
     */
    private array $decreaseList = [];

    #[AsEventListener(event: KernelEvents::FINISH_REQUEST, priority: -999)]
    public function flushCounter(): void
    {
        try {
            while ([] !== $this->increaseList) {
                $className = array_shift($this->increaseList);
                $this->countProvider->increaseEntityCounter($className);
            }
            while ([] !== $this->decreaseList) {
                $className = array_shift($this->decreaseList);
                $this->countProvider->decreaseEntityCounter($className);
            }
        } catch (\Throwable $exception) {
            $this->logger->error('更新请求中所有计数器失败', [
                'exception' => $exception,
            ]);
        }
    }

    public function postPersist(PostPersistEventArgs $eventArgs): void
    {
        $className = ClassUtils::getRealClass(get_class($eventArgs->getObject()));
        $this->increaseList[] = $className;
    }

    public function postRemove(PostRemoveEventArgs $eventArgs): void
    {
        $className = ClassUtils::getRealClass(get_class($eventArgs->getObject()));
        $this->decreaseList[] = $className;
    }

    public function reset(): void
    {
        $this->increaseList = [];
        $this->decreaseList = [];
    }
}

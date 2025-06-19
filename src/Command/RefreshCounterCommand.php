<?php

namespace CounterBundle\Command;

use Carbon\CarbonImmutable;
use CounterBundle\Entity\Counter;
use CounterBundle\Provider\CounterProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Tourze\LockCommandBundle\Command\LockableCommand;
use Tourze\Symfony\CronJob\Attribute\AsCronTask;

#[AsCronTask('30 * * * *')]
#[AsCommand(name: self::NAME, description: '定期更新计时器')]
class RefreshCounterCommand extends LockableCommand
{
    public const NAME = 'counter:refresh-counter';
    public function __construct(
        #[TaggedIterator('app.counter.provider')] private readonly iterable $providers,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var CounterProvider $provider */
        foreach ($this->providers as $provider) {
            foreach ($provider->getCounters() as $counter) {
                /** @var Counter|null $counter */
                // 有一些计数器我们丢异步跑了，所以这里会拿不到
                if ($counter === null) {
                    continue;
                }
                $now = CarbonImmutable::now();
                $output->writeln("更新计数器[{$counter->getName()}] -> {$counter->getCount()} at " . $now->toDateTimeString());
                $this->entityManager->detach($counter);
            }
        }

        return Command::SUCCESS;
    }
}

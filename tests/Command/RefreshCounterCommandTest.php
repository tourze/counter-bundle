<?php

namespace CounterBundle\Tests\Command;

use CounterBundle\Command\RefreshCounterCommand;
use CounterBundle\Entity\Counter;
use CounterBundle\Provider\CounterProvider;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class RefreshCounterCommandTest extends TestCase
{
    public function testExecute(): void
    {
        $counter = $this->createMock(Counter::class);
        $counter->method('getName')->willReturn('test_counter');
        $counter->method('getCount')->willReturn(42);

        $provider = $this->createMock(CounterProvider::class);
        $provider->method('getCounters')->willReturn([$counter]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('detach')->with($counter);

        $command = new RefreshCounterCommand([$provider], $entityManager);
        $commandTester = new CommandTester($command);
        
        $commandTester->execute([]);

        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertStringContainsString('更新计数器[test_counter] -> 42', $commandTester->getDisplay());
    }

    public function testExecuteWithNullCounter(): void
    {
        $provider = $this->createMock(CounterProvider::class);
        $provider->method('getCounters')->willReturn([null]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('detach');

        $command = new RefreshCounterCommand([$provider], $entityManager);
        $commandTester = new CommandTester($command);
        
        $commandTester->execute([]);

        $this->assertSame(0, $commandTester->getStatusCode());
    }
}
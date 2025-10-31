<?php

namespace CounterBundle\Tests\Command;

use CounterBundle\Command\RefreshCounterCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[CoversClass(RefreshCounterCommand::class)]
#[RunTestsInSeparateProcesses]
final class RefreshCounterCommandTest extends AbstractCommandTestCase
{
    private RefreshCounterCommand $command;

    protected function getCommandTester(): CommandTester
    {
        return new CommandTester($this->command);
    }

    protected function onSetUp(): void
    {
        // 从容器获取命令（使用真实的服务配置）
        $command = self::getService(RefreshCounterCommand::class);
        $this->assertInstanceOf(RefreshCounterCommand::class, $command);
        $this->command = $command;
    }

    public function testExecuteCommandConfiguration(): void
    {
        // 测试命令基本配置
        $this->assertSame('counter:refresh-counter', $this->command->getName());
        $this->assertSame('定期更新计时器', $this->command->getDescription());
    }

    public function testExecuteCommandSuccessfully(): void
    {
        // 测试命令成功执行（即使没有提供者注册）
        $commandTester = $this->getCommandTester();
        $exitCode = $commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        // 因为命令使用真实的 providers 注入，输出可能有或没有内容
        // 这里只验证命令能成功执行
    }
}

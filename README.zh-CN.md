# Counter Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/counter-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/counter-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/counter-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/counter-bundle)

一个基于 Symfony 的计数器管理系统，提供了一套完整的实体计数功能，支持自动统计、手动递增/递减、定时更新等特性。

## 功能特性

- 自动实体计数，针对大表进行性能优化
- 支持手动管理计数器，包括递增和递减操作
- 通过 cron 任务进行定时更新
- 支持上下文感知的计数追踪
- 易于集成到 Symfony 管理界面
- 支持自定义计数器提供者

## 安装

```bash
composer require tourze/counter-bundle
```

## 快速开始

1. 在 `config/bundles.php` 中注册 bundle：

```php
return [
    // ...
    CounterBundle\CounterBundle::class => ['all' => true],
];
```

2. 创建自定义计数器提供者：

```php
use CounterBundle\Provider\CounterProvider;
use CounterBundle\Entity\Counter;

#[AutoconfigureTag('app.counter.provider')]
class CustomCounterProvider implements CounterProvider
{
    public function getCounters(): iterable
    {
        yield new Counter('users.total', '用户总数');
        yield new Counter('orders.total', '订单总数');
    }
}
```

3. 在服务中使用计数器：

```php
use CounterBundle\Repository\CounterRepository;

class YourService
{
    public function __construct(
        private readonly CounterRepository $counterRepository
    ) {}

    public function getStatistics(): array
    {
        return [
            'users' => $this->counterRepository->findOneBy(['name' => 'users.total'])?->getCount() ?? 0,
            'orders' => $this->counterRepository->findOneBy(['name' => 'orders.total'])?->getCount() ?? 0,
        ];
    }
}
```

## 高级特性

### 自动实体计数

Bundle 通过 Doctrine 事件自动追踪实体计数：

```php
use CounterBundle\Provider\EntityTotalCountProvider;

class YourEntity
{
    // 你的实体定义
}

// 实体的创建/删除会自动更新计数器
```

### 性能优化

对于大表（>100万记录），Bundle 使用 `information_schema` 进行计数估算：

```php
// 自动切换到估算模式用于大表
$counter = $counterRepository->findOneBy(['name' => 'large.table.total']);
```

### 定时更新

计数器值通过 cron 任务自动更新：

```php
// 每小时的第30分钟运行
#[AsCronTask('30 * * * *')]
#[AsCommand(name: 'counter:refresh-counter')]
class RefreshCounterCommand extends LockableCommand
{
    // 命令实现
}
```

## 贡献

详情请查看 [CONTRIBUTING.md](CONTRIBUTING.md)。

## 开源协议

MIT 开源协议。详情请查看 [License 文件](LICENSE)。

# Counter Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/counter-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/counter-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/counter-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/counter-bundle)
[![PHP Version](https://img.shields.io/packagist/php-v/tourze/counter-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/counter-bundle)
[![License](https://img.shields.io/packagist/l/tourze/counter-bundle.svg?style=flat-square)](LICENSE)
[![Code Coverage](https://img.shields.io/codecov/c/github/tourze/counter-bundle?style=flat-square)](https://codecov.io/gh/tourze/counter-bundle)

一个高性能的 Symfony 计数器管理包，提供自动实体计数、手动操作和定时更新等功能。

## 目录

- [功能特性](#功能特性)
- [安装](#安装)
- [系统要求](#系统要求)
- [配置](#配置)
- [使用方法](#使用方法)
- [高级功能](#高级功能)
- [架构设计](#架构设计)
- [最佳实践](#最佳实践)
- [故障排除](#故障排除)
- [贡献](#贡献)
- [开源协议](#开源协议)

## 功能特性

- 🚀 **自动实体计数** - 通过 Doctrine 事件自动跟踪实体数量
- 📊 **性能优化** - 大表（>100万记录）自动使用估算方式
- 🔄 **实时更新** - 实体变化时计数器自动更新
- ⏰ **定时刷新** - 基于 Cron 的计数器同步机制
- 🎯 **自定义提供者** - 可扩展的计数器提供者系统
- 🔒 **线程安全** - 锁机制防止并发更新冲突
- 📝 **上下文支持** - 可存储额外的元数据信息

## 安装

```bash
composer require tourze/counter-bundle
```

## 系统要求

- PHP 8.1+
- Symfony 6.4+
- Doctrine ORM 3.0+

## 配置

### 1. 启用 Bundle

在 `config/bundles.php` 中注册：

```php
return [
    // ...
    CounterBundle\CounterBundle::class => ['all' => true],
];
```

### 2. 更新数据库结构

```bash
# 创建计数器表
php bin/console doctrine:schema:update --force

# 或使用数据库迁移
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

## 使用方法

### 基本计数器操作

```php
use CounterBundle\Repository\CounterRepository;
use CounterBundle\Provider\EntityTotalCountProvider;

class StatisticsService
{
    public function __construct(
        private readonly CounterRepository $counterRepository,
        private readonly EntityTotalCountProvider $countProvider
    ) {}

    public function getStatistics(): array
    {
        // 通过名称获取计数器
        $userCounter = $this->counterRepository->findOneBy([
            'name' => 'App\Entity\User::total'
        ]);
        
        // 手动增加/减少
        $this->countProvider->increaseEntityCounter('App\Entity\Product');
        $this->countProvider->decreaseEntityCounter('App\Entity\Product');
        
        return [
            'users' => $userCounter?->getCount() ?? 0,
            'products' => $this->counterRepository->findOneBy([
                'name' => 'App\Entity\Product::total'
            ])?->getCount() ?? 0,
        ];
    }
}
```

### 自动实体计数

Bundle 通过 Doctrine 监听器自动跟踪实体数量：

```php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;
    
    // ... 其他属性
}

// 自动创建的计数器：
// - "App\Entity\Product::total" - 产品总数
```

### 自定义计数器提供者

通过实现 `CounterProvider` 接口创建自定义计数器：

```php
use CounterBundle\Provider\CounterProvider;
use CounterBundle\Entity\Counter;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.counter.provider')]
class OrderStatisticsProvider implements CounterProvider
{
    public function __construct(
        private readonly OrderRepository $orderRepository
    ) {}

    public function getCounters(): iterable
    {
        // 待处理订单计数器
        $pendingCount = $this->orderRepository->count(['status' => 'pending']);
        $pendingCounter = new Counter();
        $pendingCounter->setName('orders.pending')
                      ->setCount($pendingCount)
                      ->setContext(['status' => 'pending']);
        yield $pendingCounter;
        
        // 已完成订单计数器
        $completedCount = $this->orderRepository->count(['status' => 'completed']);
        $completedCounter = new Counter();
        $completedCounter->setName('orders.completed')
                         ->setCount($completedCount)
                         ->setContext(['status' => 'completed']);
        yield $completedCounter;
    }
}
```

### 定时更新

计数器每小时的第30分钟自动刷新：

```bash
# 手动运行刷新命令
php bin/console counter:refresh-counter

# 或设置 cron 任务
30 * * * * php /path/to/project/bin/console counter:refresh-counter
```

### 性能优化

Bundle 会自动为大表优化计数方式：

```php
// 小于100万记录的表：使用 COUNT(*)
// 大于100万记录的表：使用 information_schema 的表统计信息

$counter = $this->countProvider->getCounterByEntityClass('App\Entity\LargeTable');
// 自动使用估算方式以提升性能
```

## 高级功能

### 上下文存储

为计数器存储额外的元数据：

```php
$counter = new Counter();
$counter->setName('api.requests')
        ->setCount(1000)
        ->setContext([
            'endpoint' => '/api/users',
            'method' => 'GET',
            'date' => '2024-01-01'
        ]);
```

### 事件监听器

Bundle 提供了用于自动计数的事件订阅者：

- `EntityListener` - 跟踪实体的创建/删除
- 实现 `ResetInterface` 用于内存管理
- 使用批处理最小化数据库查询

### 控制台命令

```bash
# 刷新所有计数器
php bin/console counter:refresh-counter

# 该命令支持锁机制，防止并发执行
```

## 架构设计

### 组件说明

- **Entity/Counter** - 带时间戳支持的主计数器实体
- **Repository/CounterRepository** - 计数器操作仓库
- **Provider/EntityTotalCountProvider** - 处理实体计数逻辑
- **EventSubscriber/EntityListener** - 跟踪 Doctrine 事件
- **Command/RefreshCounterCommand** - 定时更新计数器

### 数据库结构

```sql
CREATE TABLE table_count (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    count INT NOT NULL DEFAULT 0,
    context JSON,
    create_time DATETIME NOT NULL,
    update_time DATETIME NOT NULL
);
```

## 最佳实践

1. **复杂计数使用提供者** - 不要实时计算复杂查询的计数
2. **善用上下文** - 在上下文中存储过滤条件便于调试
3. **监控性能** - 检查日志中大表的估算警告
4. **定期更新** - 确保 cron 任务正常运行以保证计数准确

## 故障排除

### 计数器未更新

1. 检查实体监听器是否已注册：
   ```bash
   php bin/console debug:event-dispatcher doctrine.orm.entity_manager
   ```

2. 验证 cron 任务是否运行：
   ```bash
   php bin/console debug:command counter:refresh-counter
   ```

### 性能问题

1. 检查表大小：
   ```sql
   SELECT table_name, table_rows 
   FROM information_schema.tables 
   WHERE table_schema = 'your_database';
   ```

2. 在 Symfony 分析器中监控查询性能

## 贡献

详情请查看 [CONTRIBUTING.md](CONTRIBUTING.md)。

## 开源协议

MIT 开源协议。详情请查看 [License 文件](LICENSE)。

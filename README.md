# CounterBundle

CounterBundle 是一个基于 Symfony 的计数器管理系统，提供了一套完整的实体计数功能，支持自动统计、手动递增/递减、定时更新等特性。

## 核心功能

### 1. 计数器管理

提供了完整的计数器实体管理功能：

```php
use CounterBundle\Entity\Counter;

class Counter
{
    #[ListColumn]
    private ?string $name = null;  // 计数器名称
    
    #[ListColumn]
    private ?int $count = 0;      // 计数值
    
    private ?array $context = null; // 上下文信息
}
```

### 2. 计数器提供者

通过 Provider 接口定义计数器来源：

```php
use CounterBundle\Provider\CounterProvider;

#[AutoconfigureTag('app.counter.provider')]
class CustomCounterProvider implements CounterProvider
{
    public function getCounters(): iterable
    {
        // 返回需要统计的计数器列表
        yield new Counter('users.total', '用户总数');
        yield new Counter('orders.total', '订单总数');
    }
}
```

### 3. 自动统计

内置了实体总数自动统计功能：

```php
use CounterBundle\Provider\EntityTotalCountProvider;

class EntityTotalCountProvider implements CounterProvider
{
    private const FORMAT = '%s.total'; // 计数器名称格式
    
    public function getCounters(): iterable
    {
        // 自动统计所有实体的总数
        foreach ($this->getEntities() as $entity) {
            yield $this->createCounter($entity);
        }
    }
}
```

### 4. 定时更新

通过 Cron 任务定期更新计数器：

```php
use Tourze\Symfony\CronJob\Attribute\AsCronTask;

#[AsCronTask('30 * * * *')]
#[AsCommand(name: 'counter:refresh-counter')]
class RefreshCounterCommand extends LockableCommand
{
    // 每小时第30分钟执行更新
}
```

## 使用示例

### 1. 创建自定义计数器

```php
use CounterBundle\Entity\Counter;
use CounterBundle\Repository\CounterRepository;

class OrderService
{
    public function __construct(
        private readonly CounterRepository $counterRepository
    ) {}
    
    public function incrementOrderCount(): void
    {
        $counter = $this->counterRepository->findOneBy(['name' => 'orders.total']);
        if (!$counter) {
            $counter = new Counter();
            $counter->setName('orders.total');
            $counter->setCount(1);
        } else {
            $counter->setCount($counter->getCount() + 1);
        }
        $this->counterRepository->save($counter);
    }
}
```

### 2. 实现自定义计数器提供者

```php
use CounterBundle\Provider\CounterProvider;
use CounterBundle\Entity\Counter;

class OrderCounterProvider implements CounterProvider
{
    public function getCounters(): iterable
    {
        // 返回订单相关的计数器
        yield $this->createOrderTotalCounter();
        yield $this->createOrderPendingCounter();
        yield $this->createOrderCompletedCounter();
    }
    
    private function createOrderTotalCounter(): Counter
    {
        $counter = new Counter();
        $counter->setName('orders.total');
        $counter->setCount($this->calculateTotalOrders());
        return $counter;
    }
}
```

### 3. 查询计数器数据

```php
use CounterBundle\Repository\CounterRepository;

class DashboardController
{
    public function __construct(
        private readonly CounterRepository $counterRepository
    ) {}
    
    public function statistics(): array
    {
        return [
            'users' => $this->counterRepository->findOneBy(['name' => 'users.total'])?->getCount() ?? 0,
            'orders' => $this->counterRepository->findOneBy(['name' => 'orders.total'])?->getCount() ?? 0
        ];
    }
}
```

## 重要说明

1. **性能优化**
   - 大数据量表（>100万）使用 information_schema 估算
   - 支持增量更新避免全表扫描
   - 使用锁机制防止并发更新

2. **扩展性**
   - 支持自定义计数器提供者
   - 支持自定义更新策略
   - 支持上下文信息存储

3. **可靠性**
   - 异常处理和日志记录
   - 定时任务失败重试
   - 数据一致性保护

4. **使用建议**
   - 合理设置更新频率
   - 注意大表的性能影响
   - 正确处理并发情况

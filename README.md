# Counter Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/counter-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/counter-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/counter-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/counter-bundle)
[![PHP Version](https://img.shields.io/packagist/php-v/tourze/counter-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/counter-bundle)
[![License](https://img.shields.io/packagist/l/tourze/counter-bundle.svg?style=flat-square)](LICENSE)
[![Code Coverage](https://img.shields.io/codecov/c/github/tourze/counter-bundle?style=flat-square)](https://codecov.io/gh/tourze/counter-bundle)

A high-performance Symfony bundle for managing counters and statistics with automatic entity counting, manual operations, and scheduled updates.

## Table of Contents

- [Features](#features)
- [Installation](#installation)
- [Requirements](#requirements)
- [Configuration](#configuration)
- [Usage](#usage)
- [Advanced Features](#advanced-features)
- [Architecture](#architecture)
- [Best Practices](#best-practices)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)
- [License](#license)

## Features

- 🚀 **Automatic Entity Counting** - Tracks entity counts through Doctrine events
- 📊 **Performance Optimized** - Uses estimation for large tables (>1M records)
- 🔄 **Real-time Updates** - Counters update automatically on entity changes
- ⏰ **Scheduled Refresh** - Cron-based counter synchronization
- 🎯 **Custom Providers** - Extensible counter provider system
- 🔒 **Thread-safe Operations** - Lock mechanism prevents concurrent updates
- 📝 **Context Support** - Store additional metadata with counters

## Installation

```bash
composer require tourze/counter-bundle
```

## Requirements

- PHP 8.1+
- Symfony 6.4+
- Doctrine ORM 3.0+

## Configuration

### 1. Enable the Bundle

Register the bundle in `config/bundles.php`:

```php
return [
    // ...
    CounterBundle\CounterBundle::class => ['all' => true],
];
```

### 2. Update Database Schema

```bash
# Create the counter table
php bin/console doctrine:schema:update --force

# Or use migrations
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

## Usage

### Basic Counter Operations

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
        // Get counter by name
        $userCounter = $this->counterRepository->findOneBy([
            'name' => 'App\Entity\User::total'
        ]);
        
        // Manual increment/decrement
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

### Automatic Entity Counting

The bundle automatically tracks entity counts through Doctrine listeners:

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
    
    // ... other properties
}

// Counters are automatically created:
// - "App\Entity\Product::total" - Total count of products
```

### Custom Counter Providers

Create custom counters by implementing `CounterProvider`:

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
        // Pending orders counter
        $pendingCount = $this->orderRepository->count(['status' => 'pending']);
        $pendingCounter = new Counter();
        $pendingCounter->setName('orders.pending')
                      ->setCount($pendingCount)
                      ->setContext(['status' => 'pending']);
        yield $pendingCounter;
        
        // Completed orders counter
        $completedCount = $this->orderRepository->count(['status' => 'completed']);
        $completedCounter = new Counter();
        $completedCounter->setName('orders.completed')
                         ->setCount($completedCount)
                         ->setContext(['status' => 'completed']);
        yield $completedCounter;
    }
}
```

### Scheduled Updates

Counters are automatically refreshed every hour at minute 30:

```bash
# Run the refresh command manually
php bin/console counter:refresh-counter

# Or set up cron job
30 * * * * php /path/to/project/bin/console counter:refresh-counter
```

### Performance Optimization

The bundle automatically optimizes counting for large tables:

```php
// For tables with <1M records: uses COUNT(*)
// For tables with >1M records: uses table statistics from information_schema

$counter = $this->countProvider->getCounterByEntityClass(
    'App\Entity\LargeTable'
);
// Automatically uses estimation for performance
```

## Advanced Features

### Context Storage

Store additional metadata with counters:

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

### Event Listeners

The bundle provides event subscribers for automatic counting:

- `EntityListener` - Tracks entity creation/deletion
- Implements `ResetInterface` for memory management
- Uses batch processing to minimize database queries

### Console Commands

```bash
# Refresh all counters
php bin/console counter:refresh-counter

# The command is lockable to prevent concurrent execution
```

## Architecture

### Components

- **Entity/Counter** - The main counter entity with timestamp support
- **Repository/CounterRepository** - Repository for counter operations
- **Provider/EntityTotalCountProvider** - Handles entity counting logic
- **EventSubscriber/EntityListener** - Tracks Doctrine events
- **Command/RefreshCounterCommand** - Scheduled counter updates

### Database Schema

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

## Best Practices

1. **Use providers for complex counters** - Don't calculate counts in real-time for complex queries
2. **Leverage context** - Store filtering criteria in context for debugging
3. **Monitor performance** - Check logs for estimation warnings on large tables
4. **Regular updates** - Ensure cron job is running for accurate counts

## Troubleshooting

### Counters not updating

1. Check if entity listener is registered:
   ```bash
   php bin/console debug:event-dispatcher doctrine.orm.entity_manager
   ```

2. Verify cron job is running:
   ```bash
   php bin/console debug:command counter:refresh-counter
   ```

### Performance issues

1. Check table sizes:
   ```sql
   SELECT table_name, table_rows 
   FROM information_schema.tables 
   WHERE table_schema = 'your_database';
   ```

2. Monitor query performance in Symfony profiler

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

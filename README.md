# Counter Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/counter-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/counter-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/counter-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/counter-bundle)

A Symfony bundle that provides a complete entity counting system with features like automatic statistics, manual increment/decrement, and scheduled updates.

## Features

- Automatic entity counting with performance optimization for large tables
- Manual counter management with increment/decrement support
- Scheduled counter updates via cron jobs
- Context-aware counter tracking
- Easy integration with Symfony's admin interface
- Support for custom counter providers

## Installation

```bash
composer require tourze/counter-bundle
```

## Quick Start

1. Register the bundle in `config/bundles.php`:

```php
return [
    // ...
    CounterBundle\CounterBundle::class => ['all' => true],
];
```

2. Create a custom counter provider:

```php
use CounterBundle\Provider\CounterProvider;
use CounterBundle\Entity\Counter;

#[AutoconfigureTag('app.counter.provider')]
class CustomCounterProvider implements CounterProvider
{
    public function getCounters(): iterable
    {
        yield new Counter('users.total', 'Total Users');
        yield new Counter('orders.total', 'Total Orders');
    }
}
```

3. Use the counter in your service:

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

## Advanced Features

### Automatic Entity Counting

The bundle automatically tracks entity counts through Doctrine events:

```php
use CounterBundle\Provider\EntityTotalCountProvider;

class YourEntity
{
    // Your entity definition
}

// The counter will be automatically updated on entity creation/deletion
```

### Performance Optimization

For large tables (>1M records), the bundle uses `information_schema` for count estimation:

```php
// Automatically switches to estimation for large tables
$counter = $counterRepository->findOneBy(['name' => 'large.table.total']);
```

### Scheduled Updates

Counter values are automatically updated via a cron job:

```php
// Runs every hour at minute 30
#[AsCronTask('30 * * * *')]
#[AsCommand(name: 'counter:refresh-counter')]
class RefreshCounterCommand extends LockableCommand
{
    // Command implementation
}
```

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

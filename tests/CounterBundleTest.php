<?php

declare(strict_types=1);

namespace CounterBundle\Tests;

use CounterBundle\CounterBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;

/**
 * @internal
 */
#[CoversClass(CounterBundle::class)]
#[RunTestsInSeparateProcesses]
final class CounterBundleTest extends AbstractBundleTestCase
{
}

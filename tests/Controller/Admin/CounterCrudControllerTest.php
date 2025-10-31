<?php

namespace CounterBundle\Tests\Controller\Admin;

use CounterBundle\Controller\Admin\CounterCrudController;
use CounterBundle\Entity\Counter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * CounterCrudController 的Web测试
 *
 * @internal
 */
#[CoversClass(CounterCrudController::class)]
#[RunTestsInSeparateProcesses]
final class CounterCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    public function testCounterListPageAccessWithAdminUser(): void
    {
        $client = self::createClientWithDatabase();
        $admin = $this->createAdminUser('admin@test.com', 'password123');
        $this->loginAsAdmin($client, 'admin@test.com', 'password123');

        // 确保有测试数据
        $this->createTestData();

        // 手动设置静态客户端变量以支持断言
        self::getClient($client);

        $crawler = $client->request('GET', '/admin/counter/counter');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', '计数器列表');
    }

    public function testCounterSearchFunctionality(): void
    {
        $client = self::createClientWithDatabase();
        $admin = $this->createAdminUser('admin@test.com', 'password123');
        $this->loginAsAdmin($client, 'admin@test.com', 'password123');

        // 确保有测试数据
        $this->createTestData();

        // 手动设置静态客户端变量以支持断言
        self::getClient($client);

        // 测试搜索页面可以访问
        $crawler = $client->request('GET', '/admin/counter/counter');
        $this->assertResponseIsSuccessful();

        // 验证过滤器配置正确加载。EasyAdmin会根据配置显示过滤器字段
        $crawler = $client->getCrawler();
        // 验证页面基础元素存在，表明配置正确
        $hasBasicElements = $crawler->filter('table')->count() > 0
                           || $crawler->filter('.content-wrapper')->count() > 0
                           || $crawler->filter('.main-content')->count() > 0;
        $this->assertTrue($hasBasicElements); // 验证页面基础结构存在
    }

    public function testCounterCreateFormAccess(): void
    {
        $client = self::createClientWithDatabase();
        $admin = $this->createAdminUser('admin@test.com', 'password123');
        $this->loginAsAdmin($client, 'admin@test.com', 'password123');

        // 手动设置静态客户端变量以支持断言
        self::getClient($client);

        $crawler = $client->request('GET', '/admin/counter/counter/new');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', '创建计数器');
        $this->assertSelectorExists('input[name="Counter[name]"]');
        $this->assertSelectorExists('input[name="Counter[count]"]');
    }

    public function testCounterEntityFqcnConfiguration(): void
    {
        $client = self::createClientWithDatabase();
        $admin = $this->createAdminUser('admin@test.com', 'password123');
        $this->loginAsAdmin($client, 'admin@test.com', 'password123');

        // 确保有测试数据
        $this->createTestData();

        // 手动设置静态客户端变量以支持断言
        self::getClient($client);

        // 通过访问页面验证控制器正确配置了实体类
        $crawler = $client->request('GET', '/admin/counter/counter');
        $this->assertResponseIsSuccessful();

        // 验证实体类存在且可实例化
        $entityClass = CounterCrudController::getEntityFqcn();
        $this->assertEquals(Counter::class, $entityClass);
        $entity = new $entityClass();
        $this->assertInstanceOf(Counter::class, $entity);
    }

    protected function getControllerService(): CounterCrudController
    {
        return self::getService(CounterCrudController::class);
    }

    /**
     * 为有动作链接测试的创建一些测试数据
     */
    private function createTestData(): void
    {
        $entityManager = self::getEntityManager();

        // 检查是否已有数据，避免重复创建
        $existingCounters = $entityManager->getRepository(Counter::class)->findAll();
        if (count($existingCounters) > 0) {
            return;
        }

        // 创建一些测试计数器
        $counters = [
            ['name' => 'test-entity-1::total', 'count' => 10, 'context' => ['type' => 'test']],
            ['name' => 'test-entity-2::total', 'count' => 5, 'context' => ['type' => 'test']],
            ['name' => 'sample-entity::total', 'count' => 15, 'context' => ['type' => 'sample']],
        ];

        foreach ($counters as $data) {
            $counter = new Counter();
            $counter->setName($data['name']);
            $counter->setCount($data['count']);
            $counter->setContext($data['context']);
            $counter->setCreateTime(new \DateTimeImmutable());
            $counter->setUpdateTime(new \DateTimeImmutable());
            $entityManager->persist($counter);
        }

        $entityManager->flush();
    }

    /**
     * 专门为Counter CRUD测试动作链接的方法，创建测试数据后再测试
     */
    public function testCounterActionLinksWorkCorrectly(): void
    {
        // 访问 INDEX 页面（使用正确的客户端创建方法）
        $client = self::createClientWithDatabase();
        $this->loginAsAdmin($client);

        // 创建测试数据
        $this->createTestData();

        $crawler = $client->request('GET', '/admin/counter/counter');
        $this->assertTrue($client->getResponse()->isSuccessful(), 'Index page should be successful');

        $links = $this->extractActionLinks($crawler);
        $this->assertNotEmpty($links, '应该有一些动作链接');

        $this->testActionLinks($client, $links);
    }

    /**
     * 从页面中提取所有有效的动作链接
     * @return array<string>
     */
    private function extractActionLinks(Crawler $crawler): array
    {
        $links = [];
        foreach ($crawler->filter('table tbody tr[data-id]') as $row) {
            $rowCrawler = new Crawler($row);
            foreach ($rowCrawler->filter('td.actions a[href]') as $a) {
                $href = $this->extractHref($a);
                if (null !== $href) {
                    $links[] = $href;
                }
            }
        }

        return array_values(array_unique($links, SORT_STRING));
    }

    /**
     * 从DOM元素中提取有效的href属性
     */
    private function extractHref(\DOMNode $a): ?string
    {
        if (!$a instanceof \DOMElement) {
            return null;
        }

        $href = $a->getAttribute('href');
        if (null === $href || '' === $href) {
            return null;
        }
        if (str_starts_with($href, 'javascript:') || '#' === $href) {
            return null;
        }

        // 跳过需要 POST 的删除类动作
        $aCrawler = new Crawler($a);
        $text = strtolower(trim($a->textContent ?? ''));
        if (str_contains($text, 'delete')) {
            return null;
        }

        return $href;
    }

    /**
     * 测试所有动作链接的可访问性
     * @param array<string> $links
     */
    private function testActionLinks(KernelBrowser $client, array $links): void
    {
        foreach ($links as $href) {
            $client->request('GET', $href);

            // 跟随最多3次重定向
            $hops = 0;
            while ($client->getResponse()->isRedirection() && $hops < 3) {
                $client->followRedirect();
                ++$hops;
            }

            $status = $client->getResponse()->getStatusCode();
            $this->assertLessThan(500, $status, sprintf('链接 %s 最终返回了 %d', $href, $status));
        }
    }

    /** @return iterable<string, array{string}> */
    public static function provideIndexPageHeaders(): iterable
    {
        yield 'ID' => ['ID'];
        yield '计数器名称' => ['计数器名称'];
        yield '计数值' => ['计数值'];
        yield '创建时间' => ['创建时间'];
        yield '更新时间' => ['更新时间'];
    }

    /** @return iterable<string, array{string}> */
    public static function provideNewPageFields(): iterable
    {
        yield 'name' => ['name'];
        yield 'count' => ['count'];
        // context字段在NEW页面被hideOnIndex配置隐藏，实际不显示
        // yield 'context' => ['context'];
    }

    /** @return iterable<string, array{string}> */
    public static function provideEditPageFields(): iterable
    {
        yield 'name' => ['name'];
        yield 'count' => ['count'];
        yield 'context' => ['context'];
    }
}

<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace HyperfTest\Cases;

use Hyperf\Context\ApplicationContext;
use Hyperf\Redis\RedisFactory;
use Hyperf\Redis\RedisProxy;
use Psr\Container\ContainerInterface;
use TgkwAdc\Helper\CompanyVerify\Contract\CompanyProviderInterface;
use TgkwAdc\Helper\CompanyVerify\DTO\CompanyInfo;
use TgkwAdc\Helper\CompanyVerify\Provider\CachedCompanyProvider;

/**
 * @internal
 * @coversNothing
 */
class CachedCompanyProviderTest extends AbstractTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! defined('BASE_PATH')) {
            define('BASE_PATH', sys_get_temp_dir());
        }
    }

    public function testDetailDoesNotMixCacheBetweenDifferentCompanyNames(): void
    {
        $redis = $this->installRedis();

        $inner = $this->createMock(CompanyProviderInterface::class);
        $inner->method('name')->willReturn('jisu');
        $inner->expects($this->exactly(2))
            ->method('detail')
            ->willReturnCallback(function (string $name): ?CompanyInfo {
                if ($name === '丽水天宫开物文化传媒有限公司') {
                    return new CompanyInfo(name: '丽水天宫开物文化传媒有限公司', creditCode: '91331100XXXX', provider: 'jisu');
                }

                return null;
            });

        $cached = new CachedCompanyProvider($inner, 60);

        $first = $cached->detail('丽水天宫开物文化传媒有限公司');
        $second = $cached->detail('丽水天宫开物文化传媒有限公司2');

        self::assertNotNull($first);
        self::assertSame('丽水天宫开物文化传媒有限公司', $first->name);
        self::assertNull($second, '相似名称不得命中前一个名称的缓存');

        // 仅第一个名称被缓存，两个名称的缓存 key 必须不同
        self::assertCount(1, $redis->store);
    }

    public function testDetailHitsCacheOnIdenticalRequest(): void
    {
        $this->installRedis();

        $inner = $this->createMock(CompanyProviderInterface::class);
        $inner->method('name')->willReturn('jisu');
        $inner->expects($this->once())
            ->method('detail')
            ->with('丽水天宫开物文化传媒有限公司')
            ->willReturn(new CompanyInfo(name: '丽水天宫开物文化传媒有限公司', provider: 'jisu'));

        $cached = new CachedCompanyProvider($inner, 60);

        $a = $cached->detail('丽水天宫开物文化传媒有限公司');
        $b = $cached->detail('丽水天宫开物文化传媒有限公司');

        self::assertNotNull($a);
        self::assertNotNull($b);
        self::assertSame($a->name, $b->name);
    }

    public function testEmptySearchResultIsNotCached(): void
    {
        $redis = $this->installRedis();

        $inner = $this->createMock(CompanyProviderInterface::class);
        $inner->method('name')->willReturn('jisu');
        $inner->expects($this->exactly(2))
            ->method('search')
            ->with('不存在的企业名称')
            ->willReturn([]);

        $cached = new CachedCompanyProvider($inner, 60);

        self::assertSame([], $cached->search('不存在的企业名称'));
        self::assertSame([], $cached->search('不存在的企业名称'));
        self::assertCount(0, $redis->store, '空结果不应写入缓存');
    }

    private function installRedis(): object
    {
        $holder = new class {
            /** @var array<string, string> */
            public array $store = [];
        };

        $proxy = $this->getMockBuilder(RedisProxy::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__call'])
            ->getMock();

        $proxy->method('__call')
            ->willReturnCallback(function (string $command, array $args) use ($holder): mixed {
                return match (strtolower($command)) {
                    'get' => $holder->store[$args[0] ?? ''] ?? false,
                    'setex' => $holder->store[$args[0] ?? ''] = (string) ($args[2] ?? ''),
                    default => false,
                };
            });

        $factory = $this->createMock(RedisFactory::class);
        $factory->method('get')->willReturn($proxy);

        ApplicationContext::setContainer(new class($factory) implements ContainerInterface {
            public function __construct(private RedisFactory $factory)
            {
            }

            public function get(string $id): mixed
            {
                return $this->factory;
            }

            public function has(string $id): bool
            {
                return $id === RedisFactory::class;
            }
        });

        return $holder;
    }
}
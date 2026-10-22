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
use Hyperf\Contract\ConfigInterface;
use Hyperf\Logger\LoggerFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use TgkwAdc\Helper\CompanyVerify\CompanyVerifyManager;
use TgkwAdc\Helper\CompanyVerify\CompanyVerifyService;
use TgkwAdc\Helper\CompanyVerify\Contract\CompanyProviderInterface;
use TgkwAdc\Helper\CompanyVerify\DTO\CompanyInfo;

/**
 * @internal
 * @coversNothing
 */
class CompanyVerifyServiceTest extends AbstractTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! defined('BASE_PATH')) {
            define('BASE_PATH', sys_get_temp_dir());
        }

        $config = $this->createMock(ConfigInterface::class);
        $config->method('get')->willReturn('info');

        $logger = $this->createMock(LoggerInterface::class);
        $factory = $this->createMock(LoggerFactory::class);
        $factory->method('get')->willReturn($logger);

        ApplicationContext::setContainer(new class($config, $factory) implements ContainerInterface {
            public function __construct(
                private ConfigInterface $config,
                private LoggerFactory $factory,
            ) {
            }

            public function get(string $id): mixed
            {
                return match ($id) {
                    ConfigInterface::class => $this->config,
                    LoggerFactory::class => $this->factory,
                };
            }

            public function has(string $id): bool
            {
                return in_array($id, [ConfigInterface::class, LoggerFactory::class], true);
            }
        });
    }

    public function testVerifyReturnsFalseWithoutCreditCodeFallbackWhenNameMatchesButCreditCodeDiffers(): void
    {
        $provider = $this->createMock(CompanyProviderInterface::class);
        $provider->expects($this->once())
            ->method('search')
            ->with('天宫通联(深圳)科技有限公司')
            ->willReturn([
                new CompanyInfo(
                    name: '天宫通联（深圳）科技有限公司',
                    creditCode: '91440300CORRECTCODE',
                    provider: 'jisu',
                ),
            ]);
        $provider->expects($this->exactly(2))
            ->method('name')
            ->willReturn('jisu');

        $manager = $this->createMock(CompanyVerifyManager::class);
        $manager->expects($this->once())
            ->method('driverFor')
            ->with('verify')
            ->willReturn($provider);

        $result = (new CompanyVerifyService($manager))->verify([
            'company_name' => '天宫通联(深圳)科技有限公司',
            'credit_code' => '914403003544406424F',
        ]);

        self::assertSame([
            'verified' => false,
            'driver' => 'jisu',
            'company' => null,
        ], $result);
    }
}

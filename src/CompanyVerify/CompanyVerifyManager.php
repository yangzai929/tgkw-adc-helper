<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\CompanyVerify;

use TgkwAdc\CompanyVerify\Contract\CompanyProviderInterface;
use TgkwAdc\CompanyVerify\DTO\CompanyInfo;
use TgkwAdc\CompanyVerify\Exception\CompanyVerifyException;
use TgkwAdc\CompanyVerify\Provider\ShumaiDataProvider;
use TgkwAdc\CompanyVerify\Provider\TianYanChaProvider;
use TgkwAdc\Utils\ShumaiData;
use TgkwAdc\Utils\TianYanChaApi;

/**
 * 企业核验管理器（工厂 + 门面）.
 *
 * 业务系统统一通过本类查询，默认 provider 由配置 company_verify.default 决定，
 * 也可在调用时显式指定。新增三方时只需实现 {@see CompanyProviderInterface}
 * 并在 {@see CompanyVerifyManager::makeProvider()} 中注册即可。
 *
 * 用法：
 *   $m = new CompanyVerifyManager();
 *   $m->verify('中航重机股份有限公司', '91520000214434146R');     // 用默认三方
 *   $m->driver('shumai')->search('中航');                          // 指定三方
 */
class CompanyVerifyManager implements CompanyProviderInterface
{
    /**
     * 已实例化的 provider 缓存.
     *
     * @var array<string, CompanyProviderInterface>
     */
    private array $providers = [];

    /**
     * @param null|array $config 配置数组，为空时从 company_verify 配置读取
     */
    public function __construct(private ?array $config = null)
    {
        if ($this->config === null) {
            $this->config = (array) (function_exists('cfg') ? cfg('company_verify', []) : []);
        }
    }

    public function name(): string
    {
        return $this->driver()->name();
    }

    public function verify(string $companyName, ?string $creditCode = null): bool
    {
        return $this->driver()->verify($companyName, $creditCode);
    }

    public function search(string $keyword): array
    {
        return $this->driver()->search($keyword);
    }

    public function detail(string $companyName): ?CompanyInfo
    {
        return $this->driver()->detail($companyName);
    }

    /**
     * 获取指定（或默认）三方 provider.
     *
     * @param null|string $name 三方标识，为空时取配置默认
     */
    public function driver(?string $name = null): CompanyProviderInterface
    {
        $name = $name ?: $this->defaultDriver();

        return $this->providers[$name] ??= $this->makeProvider($name);
    }

    /**
     * 默认三方标识.
     */
    public function defaultDriver(): string
    {
        $default = $this->config['default'] ?? null;
        if (empty($default)) {
            throw new CompanyVerifyException('未配置默认企业核验三方（company_verify.default）');
        }

        return (string) $default;
    }

    /**
     * 构建 provider 实例.
     */
    private function makeProvider(string $name): CompanyProviderInterface
    {
        $options = (array) ($this->config['providers'][$name] ?? []);

        return match ($name) {
            'tianyancha' => new TianYanChaProvider(
                new TianYanChaApi((string) ($options['token'] ?? ''))
            ),
            'shumai' => new ShumaiDataProvider(
                new ShumaiData((string) ($options['app_code'] ?? ''))
            ),
            default => throw new CompanyVerifyException("不支持的企业核验三方: {$name}"),
        };
    }
}

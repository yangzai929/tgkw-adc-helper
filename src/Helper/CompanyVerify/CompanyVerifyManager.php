<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Helper\CompanyVerify;

use TgkwAdc\Helper\CompanyVerify\Contract\CompanyProviderInterface;
use TgkwAdc\Helper\CompanyVerify\DTO\CompanyInfo;
use TgkwAdc\Helper\CompanyVerify\Exception\CompanyVerifyException;
use TgkwAdc\Helper\CompanyVerify\Provider\ShumaiDataProvider;
use TgkwAdc\Helper\CompanyVerify\Provider\TianYanChaProvider;
use TgkwAdc\Helper\Log\LogHelper;
use TgkwAdc\Utils\ShumaiData;
use TgkwAdc\Utils\TianYanChaApi;

/**
 * 企业核验管理器（工厂 + 门面）.
 *
 * 业务系统统一通过本类查询，默认 provider 由 systemConfig.company_verify_driver 决定，
 * 也可在调用时显式指定。新增三方时只需实现 {@see CompanyProviderInterface}
 * 并在 {@see CompanyVerifyManager::makeProvider()} 中注册即可。
 *
 * systemConfig 字段：
 *   - company_verify_driver              默认三方：tianyancha | shumai
 *   - company_verify_tianyancha_token    天眼查 Token
 *   - company_verify_shumai_app_code     数脉 AppCode
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
     * @param null|array $config 配置数组，为空时从 systemConfig 读取
     */
    public function __construct(private ?array $config = null)
    {
        if ($this->config === null) {
            $this->config = self::buildConfigFromSystem();
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
            throw new CompanyVerifyException('未配置默认企业核验三方（systemConfig.company_verify_driver）');
        }

        return (string) $default;
    }

    /**
     * 从 systemConfig 组装内部配置结构.
     */
    private static function buildConfigFromSystem(): array
    {
        $systemConfig = self::getSystemConfig();

        return [
            'default' => (string) ($systemConfig['company_verify_driver'] ?? 'tianyancha'),
            'providers' => [
                'tianyancha' => [
                    'token' => (string) ($systemConfig['company_verify_tianyancha_token'] ?? ''),
                ],
                'shumai' => [
                    'app_code' => (string) ($systemConfig['company_verify_shumai_app_code'] ?? ''),
                ],
            ],
        ];
    }

    /**
     * 统一获取并解析系统配置.
     */
    private static function getSystemConfig(): array
    {
        $systemConfig = function_exists('cfg') ? cfg('systemConfig') : null;

        if (is_array($systemConfig)) {
            return $systemConfig;
        }

        if (is_string($systemConfig)) {
            $decoded = json_decode($systemConfig, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        LogHelper::error('systemConfig invalid', ['value' => $systemConfig], 'company_verify');

        return [];
    }

    /**
     * 构建 provider 实例.
     */
    private function makeProvider(string $name): CompanyProviderInterface
    {
        $options = (array) ($this->config['providers'][$name] ?? []);

        return match ($name) {
            'tianyancha' => $this->makeTianYanChaProvider($options),
            'shumai' => $this->makeShumaiProvider($options),
            default => throw new CompanyVerifyException("不支持的企业核验三方: {$name}"),
        };
    }

    private function makeTianYanChaProvider(array $options): TianYanChaProvider
    {
        $token = (string) ($options['token'] ?? '');
        if ($token === '') {
            LogHelper::error('company verify tianyancha token missing', [
                'key' => 'company_verify_tianyancha_token',
            ], 'company_verify');
            throw new CompanyVerifyException('未配置天眼查 Token（systemConfig.company_verify_tianyancha_token）');
        }

        return new TianYanChaProvider(new TianYanChaApi($token));
    }

    private function makeShumaiProvider(array $options): ShumaiDataProvider
    {
        $appCode = (string) ($options['app_code'] ?? '');
        if ($appCode === '') {
            LogHelper::error('company verify shumai app_code missing', [
                'key' => 'company_verify_shumai_app_code',
            ], 'company_verify');
            throw new CompanyVerifyException('未配置数脉 AppCode（systemConfig.company_verify_shumai_app_code）');
        }

        return new ShumaiDataProvider(new ShumaiData($appCode));
    }
}

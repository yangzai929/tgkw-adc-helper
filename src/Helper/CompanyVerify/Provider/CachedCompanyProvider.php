<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Helper\CompanyVerify\Provider;

use TgkwAdc\Helper\CompanyVerify\Contract\CompanyProviderInterface;
use TgkwAdc\Helper\CompanyVerify\DTO\CompanyInfo;
use TgkwAdc\Helper\Log\LogHelper;
use TgkwAdc\Utils\RedisUtils;
use Throwable;

/**
 * 带缓存的企业查询 Provider 装饰器.
 *
 * 对参数相同的请求，仅在有结果时写入 Redis 缓存（detail 命中、search 非空）。
 * 空结果不缓存，避免把三方临时异常或未收录状态固化。
 *
 * 缓存时长由 env('COMPANY_INFO_QUERY_CACHE_TIME')（秒）决定，未配置或 <= 0
 * 时不启用缓存。缓存读写失败仅记录日志并回源三方，不影响主流程。
 */
class CachedCompanyProvider implements CompanyProviderInterface
{
    private const KEY_PREFIX = 'company_verify:query:';

    /**
     * @param CompanyProviderInterface $provider 被包装的真实 provider
     * @param int $ttl 缓存时长（秒）
     */
    public function __construct(
        private CompanyProviderInterface $provider,
        private int $ttl,
    ) {
    }

    public function name(): string
    {
        return $this->provider->name();
    }

    public function search(string $keyword): array
    {
        $key = $this->cacheKey('search', [$keyword]);
        $cached = $this->readCache($key);
        if (is_array($cached) && array_is_list($cached)) {
            return array_map(
                static fn (array $item): CompanyInfo => CompanyInfo::fromArray($item),
                $cached
            );
        }

        $companies = $this->provider->search($keyword);
        if ($companies !== []) {
            $this->writeCache($key, array_map(
                static fn (CompanyInfo $item): array => $item->toArray(),
                $companies
            ));
        }

        return $companies;
    }

    public function detail(string $companyName): ?CompanyInfo
    {
        $key = $this->cacheKey('detail', [$companyName]);
        $cached = $this->readCache($key);
        if (is_array($cached) && ! array_is_list($cached)) {
            return CompanyInfo::fromArray($cached);
        }

        $info = $this->provider->detail($companyName);
        if ($info !== null) {
            $this->writeCache($key, $info->toArray());
        }

        return $info;
    }

    /**
     * 缓存 key：provider + 方法 + 参数摘要，参数一致即命中.
     */
    private function cacheKey(string $method, array $params): string
    {
        return self::KEY_PREFIX . $this->name() . ':' . $method . ':'
            . md5(json_encode($params, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 读取缓存，异常或未命中时返回 null.
     */
    private function readCache(string $key): ?array
    {
        try {
            $value = RedisUtils::get($key);
            if (! is_string($value) || $value === '') {
                return null;
            }

            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : null;
        } catch (Throwable $e) {
            LogHelper::warning('company verify cache read failed', [
                'key' => $key,
                'message' => $e->getMessage(),
            ], 'company_verify');

            return null;
        }
    }

    /**
     * 写入缓存，失败仅记录日志.
     */
    private function writeCache(string $key, array $payload): void
    {
        try {
            RedisUtils::setEx($key, $this->ttl, json_encode($payload, JSON_UNESCAPED_UNICODE));
        } catch (Throwable $e) {
            LogHelper::warning('company verify cache write failed', [
                'key' => $key,
                'message' => $e->getMessage(),
            ], 'company_verify');
        }
    }
}

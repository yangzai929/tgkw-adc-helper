<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Utils\CompanyVerify\Provider;

use TgkwAdc\Helper\Log\LogHelper;
use TgkwAdc\Utils\CompanyVerify\Contract\CompanyProviderInterface;
use TgkwAdc\Utils\CompanyVerify\DTO\CompanyInfo;
use TgkwAdc\Utils\CompanyVerify\Exception\CompanyVerifyException;
use TgkwAdc\Utils\ShumaiData;
use Throwable;

/**
 * 数脉数据 Provider.
 *
 * 适配 {@see ShumaiData}，将企业模糊搜索接口映射为统一契约。
 * 数脉为模糊查询，detail 取搜索命中的第一条（名称完全匹配优先）。
 */
class ShumaiDataProvider implements CompanyProviderInterface
{
    public function __construct(private ShumaiData $api)
    {
    }

    public function name(): string
    {
        return 'shumai';
    }

    public function verify(string $companyName, ?string $creditCode = null): bool
    {
        foreach ($this->search($companyName) as $item) {
            if (trim($item->name) !== trim($companyName)) {
                continue;
            }

            if ($creditCode !== null && $creditCode !== '') {
                return $this->equalsCode($item->creditCode, $creditCode);
            }

            return true;
        }

        return false;
    }

    public function search(string $keyword): array
    {
        try {
            $response = $this->api->query($keyword);
        } catch (Throwable $e) {
            LogHelper::error('shumai query failed', [
                'keyword' => $keyword,
                'message' => $e->getMessage(),
            ], 'company_verify');
            throw new CompanyVerifyException('数脉数据查询失败: ' . $e->getMessage(), 0, $e);
        }

        $list = $this->extractList($response);
        if ($list === []) {
            LogHelper::info('shumai empty result', [
                'keyword' => $keyword,
                'response_keys' => array_keys($response),
            ], 'company_verify');
        }

        $companies = [];
        foreach ($list as $item) {
            if (! is_array($item)) {
                continue;
            }
            $companies[] = $this->mapItem($item);
        }

        return $companies;
    }

    public function detail(string $companyName): ?CompanyInfo
    {
        $results = $this->search($companyName);
        if (empty($results)) {
            return null;
        }

        // 优先返回名称完全匹配的结果，否则取第一条候选
        foreach ($results as $item) {
            if (trim($item->name) === trim($companyName)) {
                return $item;
            }
        }

        return $results[0];
    }

    /**
     * 从三方响应中提取企业列表，兼容常见的包裹结构.
     */
    private function extractList(array $response): array
    {
        // 可能的结构：{data: [...]}、{data: {items: [...]}}、{result: [...]}、或直接 [...]
        $candidates = [
            $response['data']['items'] ?? null,
            $response['data']['list'] ?? null,
            $response['data'] ?? null,
            $response['result'] ?? null,
            $response['list'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_array($candidate) && array_is_list($candidate)) {
                return $candidate;
            }
        }

        return array_is_list($response) ? $response : [];
    }

    /**
     * 将单条模糊搜索结果映射为标准字段.
     */
    private function mapItem(array $item): CompanyInfo
    {
        return CompanyInfo::fromArray([
            'name' => $item['name'] ?? $item['companyName'] ?? '',
            'creditCode' => $item['creditCode'] ?? $item['creditNo'] ?? $item['unifiedCode'] ?? null,
            'legalPersonName' => $item['legalPersonName'] ?? $item['operName'] ?? $item['legalPerson'] ?? null,
            'regStatus' => $item['regStatus'] ?? $item['status'] ?? null,
            'regCapital' => $item['regCapital'] ?? null,
            'regNumber' => $item['regNumber'] ?? $item['regNo'] ?? null,
            'estiblishTime' => $item['estiblishTime'] ?? $item['startDate'] ?? $item['establishTime'] ?? null,
            'regLocation' => $item['regLocation'] ?? $item['address'] ?? null,
            'businessScope' => $item['businessScope'] ?? $item['scope'] ?? null,
            'provider' => $this->name(),
            'raw' => $item,
        ]);
    }

    private function equalsCode(?string $a, string $b): bool
    {
        return $a !== null && strcasecmp(trim($a), trim($b)) === 0;
    }
}

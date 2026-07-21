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
use TgkwAdc\Helper\CompanyVerify\Exception\CompanyVerifyException;
use TgkwAdc\Helper\Log\LogHelper;
use TgkwAdc\Utils\TianYanChaApi;
use Throwable;

/**
 * 天眼查 Provider.
 *
 * 适配 {@see TianYanChaApi}，将工商基本信息接口映射为统一契约。
 * 天眼查为精确查询，search 返回 detail 命中的单个企业（0 或 1 条）。
 */
class TianYanChaProvider implements CompanyProviderInterface
{
    public function __construct(private TianYanChaApi $api)
    {
    }

    public function name(): string
    {
        return 'tianyancha';
    }

    public function verify(string $companyName, ?string $creditCode = null): bool
    {
        $info = $this->detail($companyName);
        if ($info === null) {
            return false;
        }

        if ($creditCode !== null && $creditCode !== '') {
            return $this->equalsCode($info->creditCode, $creditCode);
        }

        return true;
    }

    public function search(string $keyword): array
    {
        $info = $this->detail($keyword);

        return $info === null ? [] : [$info];
    }

    public function detail(string $companyName): ?CompanyInfo
    {
        try {
            $response = $this->api->getCompanyBaseInfo($companyName);
        } catch (Throwable $e) {
            LogHelper::error('tianyancha query failed', [
                'company_name' => $companyName,
                'message' => $e->getMessage(),
            ], 'company_verify');
            throw new CompanyVerifyException('天眼查查询失败: ' . $e->getMessage(), 0, $e);
        }

        // error_code 为 0 表示成功，其余（如 300006 余额不足、查无结果）视为无数据
        if (($response['error_code'] ?? -1) !== 0) {
            LogHelper::warning('tianyancha response not success', [
                'company_name' => $companyName,
                'error_code' => $response['error_code'] ?? null,
                'reason' => $response['reason'] ?? null,
            ], 'company_verify');
            return null;
        }

        $result = $response['result'] ?? null;
        if (! is_array($result) || empty($result)) {
            LogHelper::info('tianyancha empty result', [
                'company_name' => $companyName,
            ], 'company_verify');
            return null;
        }

        return CompanyInfo::fromArray([
            'name' => $result['name'] ?? '',
            'creditCode' => $result['creditCode'] ?? null,
            'legalPersonName' => $result['legalPersonName'] ?? null,
            'regStatus' => $result['regStatus'] ?? null,
            'regCapital' => $result['regCapital'] ?? null,
            'regNumber' => $result['regNumber'] ?? null,
            'estiblishTime' => $this->formatTime($result['estiblishTime'] ?? null),
            'regLocation' => $result['regLocation'] ?? null,
            'businessScope' => $result['businessScope'] ?? null,
            'provider' => $this->name(),
            'raw' => $result,
        ]);
    }

    /**
     * 天眼查时间字段为毫秒时间戳，转为 Y-m-d 字符串.
     */
    private function formatTime($value): ?string
    {
        if (! is_numeric($value)) {
            return $value === null ? null : (string) $value;
        }

        return date('Y-m-d', (int) ((int) $value / 1000));
    }

    private function equalsCode(?string $a, string $b): bool
    {
        return $a !== null && strcasecmp(trim($a), trim($b)) === 0;
    }
}

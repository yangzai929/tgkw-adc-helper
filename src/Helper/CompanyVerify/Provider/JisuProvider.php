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
use TgkwAdc\Utils\CompanyInfo\JisuApi;
use Throwable;

/**
 * 极速数据（阿里云市场-企业工商信息）Provider.
 *
 * 适配 {@see JisuApi}：
 * - detail 使用精准查询接口（按工商名称全匹配）；
 * - search 使用模糊搜索接口，单条结果字段与 detail 一致，共用映射。
 */
class JisuProvider implements CompanyProviderInterface
{
    public function __construct(private JisuApi $api)
    {
    }

    public function name(): string
    {
        return 'jisu';
    }

    public function search(string $keyword): array
    {
        try {
            $response = $this->api->search($keyword);
        } catch (Throwable $e) {
            LogHelper::error('jisu search failed', [
                'keyword' => $keyword,
                'message' => $e->getMessage(),
            ], 'company_verify');
            throw new CompanyVerifyException('极速数据查询失败: ' . $e->getMessage(), 0, $e);
        }

        $result = $this->extractResult($response, 'search', ['keyword' => $keyword]);
        if ($result === null) {
            return [];
        }

        // 兼容 result 直接为列表，或 result.list 包裹两种结构
        $list = array_is_list($result) ? $result : (array) ($result['list'] ?? []);

        $companies = [];
        foreach ($list as $item) {
            if (! is_array($item)) {
                continue;
            }
            $basic = $this->unwrapBasic($item);
            if (($basic['name'] ?? '') === '') {
                continue;
            }
            $companies[] = $this->mapItem($basic);
        }

        return $companies;
    }

    public function detail(string $companyName): ?CompanyInfo
    {
        try {
            $response = $this->api->query(['company' => $companyName]);
        } catch (Throwable $e) {
            LogHelper::error('jisu query failed', [
                'company_name' => $companyName,
                'message' => $e->getMessage(),
            ], 'company_verify');
            throw new CompanyVerifyException('极速数据查询失败: ' . $e->getMessage(), 0, $e);
        }

        $result = $this->extractResult($response, 'query', ['company_name' => $companyName]);
        if ($result === null || array_is_list($result)) {
            return null;
        }

        $basic = $this->unwrapBasic($result);
        if (($basic['name'] ?? '') === '') {
            return null;
        }

        return $this->mapItem($basic);
    }

    /**
     * 解析极速数据响应信封：status 为字符串 "0" 表示成功，其余视为无数据.
     */
    private function extractResult(array $response, string $action, array $context): ?array
    {
        if (isset($response['status']) && (string) $response['status'] !== '0') {
            LogHelper::info("jisu {$action} response not success", $context + [
                'status' => $response['status'] ?? null,
                'msg' => $response['msg'] ?? null,
            ], 'company_verify');

            return null;
        }

        $result = $response['result'] ?? null;
        if (! is_array($result) || empty($result)) {
            LogHelper::info("jisu {$action} empty result", $context, 'company_verify');

            return null;
        }

        return $result;
    }

    /**
     * 将单条企业数据映射为标准字段（query 与 search 的单条结构一致）.
     */
    private function mapItem(array $item): CompanyInfo
    {
        $item = $this->unwrapBasic($item);

        return CompanyInfo::fromArray([
            'name' => $item['name'] ?? '',
            'creditCode' => $item['creditno'] ?? null,
            'legalPersonName' => $item['legalperson'] ?? null,
            'regStatus' => $item['status'] ?? null,
            'regCapital' => $item['regcapitalcn'] ?? $item['regcapital'] ?? null,
            'regNumber' => $item['regno'] ?? null,
            'estiblishTime' => $item['startdate'] ?? $item['regdate'] ?? null,
            'regLocation' => $item['regaddress'] ?? null,
            'businessScope' => $item['scope'] ?? null,
            'companyOrgType' => $item['type'] ?? null,
            'regInstitute' => $item['regorgan'] ?? null,
            'orgNumber' => $item['orgno'] ?? null,
            'actualCapital' => $item['reccap'] ?? null,
            'regCapitalCurrency' => $item['regcapitalcur'] ?? null,
            'industry' => $item['nicname'] ?? null,
            'socialStaffNum' => $item['insurenum'] ?? null,
            'businessTerm' => $this->formatBusinessTerm($item['startdate'] ?? null, $item['enddate'] ?? null),
            'provider' => $this->name(),
            'raw' => $item,
        ]);
    }

    /**
     * 兼容 result 直接为数据、或 result.basic 包裹数据两种结构.
     */
    private function unwrapBasic(array $data): array
    {
        $basic = $data['basic'] ?? null;

        return is_array($basic) ? $basic : $data;
    }

    /**
     * 营业期限：startdate/enddate 拼接为「起至止」，enddate 为空表示无固定期限.
     * @param mixed $startDate
     * @param mixed $endDate
     */
    private function formatBusinessTerm($startDate, $endDate): ?string
    {
        $start = trim((string) ($startDate ?? ''));
        if ($start === '') {
            return null;
        }

        $end = trim((string) ($endDate ?? ''));

        return $start . '至' . ($end === '' ? '无固定期限' : $end);
    }
}

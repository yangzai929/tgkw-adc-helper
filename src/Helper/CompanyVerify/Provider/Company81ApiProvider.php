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
use TgkwAdc\Utils\CompanyInfo\Company81Api;
use Throwable;

/**
 * 81api Provider.
 *
 * 适配 {@see Company81Api}：
 * - detail 使用企业工商数据精准查询接口（支持企业名称或信用代码），映射完整工商字段；
 * - search 使用企业名称搜索建议接口（模糊），仅返回企业名称列表。
 */
class Company81ApiProvider implements CompanyProviderInterface
{
    public function __construct(private Company81Api $api)
    {
    }

    public function name(): string
    {
        return '81api';
    }

    public function search(string $keyword): array
    {
        try {
            $response = $this->api->fuzzySuggestCompanyName($keyword);
        } catch (Throwable $e) {
            LogHelper::error('81api fuzzy query failed', [
                'keyword' => $keyword,
                'message' => $e->getMessage(),
            ], 'company_verify');
            throw new CompanyVerifyException('81api查询失败: ' . $e->getMessage(), 0, $e);
        }

        if (($response['status'] ?? false) !== true) {
            LogHelper::info('81api fuzzy empty result', ['keyword' => $keyword], 'company_verify');
            return [];
        }

        $list = $response['data']['list'] ?? [];
        if (! is_array($list)) {
            return [];
        }

        $companies = [];
        foreach ($list as $item) {
            if (! is_array($item) || empty($item['companyname'])) {
                continue;
            }
            $companies[] = CompanyInfo::fromArray([
                'name' => $item['companyname'],
                'provider' => $this->name(),
                'raw' => $item,
            ]);
        }

        return $companies;
    }

    public function detail(string $companyName): ?CompanyInfo
    {
        try {
            $response = $this->api->getCompanyBaseInfo($companyName);
        } catch (Throwable $e) {
            LogHelper::error('81api query failed', [
                'company_name' => $companyName,
                'message' => $e->getMessage(),
            ], 'company_verify');
            throw new CompanyVerifyException('81api查询失败: ' . $e->getMessage(), 0, $e);
        }

        if (($response['status'] ?? false) !== true) {
            LogHelper::info('81api response not success', [
                'company_name' => $companyName,
                'status' => $response['status'] ?? null,
            ], 'company_verify');
            return null;
        }

        $data = $response['data'] ?? null;
        if (! is_array($data) || empty($data)) {
            LogHelper::info('81api empty result', [
                'company_name' => $companyName,
            ], 'company_verify');
            return null;
        }

        $register = (array) ($data['registerData'] ?? []);

        return CompanyInfo::fromArray([
            'name' => $data['name'] ?? '',
            'creditCode' => $register['creditNo'] ?? null,
            'legalPersonName' => $data['legalPersonName'] ?? null,
            'regStatus' => $register['status'] ?? null,
            'regCapital' => $data['registerCapital'] ?? $register['registerCapital'] ?? null,
            'regNumber' => $register['registerNo'] ?? null,
            'estiblishTime' => $data['startDate'] ?? null,
            'regLocation' => $register['address'] ?? null,
            'businessScope' => $register['businessScope'] ?? null,
            'companyOrgType' => $register['regType'] ?? null,
            'regInstitute' => $register['belongOrg'] ?? null,
            'orgNumber' => $register['orgNo'] ?? null,
            'actualCapital' => $register['actualCapital'] ?? null,
            'regCapitalCurrency' => $register['registerCapitalCurrency'] ?? null,
            'industry' => $register['industry'] ?? null,
            'socialStaffNum' => $data['socialEmployeeNum'] ?? null,
            'businessTerm' => $register['businessTerm'] ?? null,
            'provider' => $this->name(),
            'raw' => $data,
        ]);
    }
}

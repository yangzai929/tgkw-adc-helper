<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Helper\CompanyVerify;

use TgkwAdc\Constants\Code\CommonCode;
use TgkwAdc\Exception\BusinessException;
use TgkwAdc\Helper\CompanyVerify\DTO\CompanyInfo;
use TgkwAdc\Helper\CompanyVerify\Exception\CompanyVerifyException;
use TgkwAdc\Helper\Log\LogHelper;
use Throwable;

/**
 * 企业核验业务服务（供各微服务直接注入 / make 使用）.
 *
 * 用法：
 *   make(CompanyVerifyService::class)->verify($params);
 *   // 或
 *   (new CompanyVerifyService())->verify($params);
 *
 * 三方通道由服务端 systemConfig.company_verify_driver 决定，入参不支持指定。
 */
class CompanyVerifyService
{
    /**
     * 企业名称 / 信用代码核验.
     *
     * 参数说明：
     * - company_name (必填) 企业名称，字符串，最长 200
     * - credit_code (选填) 统一社会信用代码；传则同时核验名称+代码，不传则只核验名称是否存在
     *
     * @return array{verified: bool, driver: string, company: null|array}
     */
    public function verify(array $params): array
    {
        $companyName = (string) ($params['company_name'] ?? '');
        $creditCode = isset($params['credit_code']) ? (string) $params['credit_code'] : null;
        if ($creditCode === '') {
            $creditCode = null;
        }
        return $this->run('verify', [
            'company_name' => $companyName,
            'credit_code' => $creditCode,
        ], function () use ($companyName, $creditCode): array {
            $manager = new CompanyVerifyManager();
            $provider = $manager->driverFor('verify');
            $matched = $this->findMatchedCompany($provider->search($companyName), $companyName, $creditCode);

            // 名称检索未命中且传了信用代码时，再用信用代码检索
            if ($matched === null && $creditCode !== null) {
                $matched = $this->findMatchedCompany($provider->search($creditCode), $companyName, $creditCode);
            }

            $verified = $matched !== null;

            LogHelper::info('company verify result', [
                'company_name' => $companyName,
                'credit_code' => $creditCode,
                'driver' => $provider->name(),
                'verified' => $verified,
            ], 'company_verify');

            return [
                'verified' => $verified,
                'driver' => $provider->name(),
                'company' => $verified ? $this->formatCompany($matched) : null,
            ];
        });
    }

    /**
     * 企业模糊搜索.
     *
     * 参数说明：
     * - keyword (必填) 搜索关键词，字符串，最长 200
     *
     * @return array{driver: string, list: array}
     */
    public function search(array $params): array
    {
        $keyword = (string) ($params['keyword'] ?? '');

        return $this->run('search', ['keyword' => $keyword], function () use ($keyword): array {
            $manager = new CompanyVerifyManager();
            $provider = $manager->driverFor('search');
            $companies = $provider->search($keyword);

            LogHelper::info('company search result', [
                'keyword' => $keyword,
                'driver' => $provider->name(),
                'count' => count($companies),
            ], 'company_verify');

            return [
                'driver' => $provider->name(),
                'list' => array_map(
                    fn (CompanyInfo $item): array => $this->formatCompany($item),
                    $companies
                ),
            ];
        });
    }

    /**
     * 企业工商详情查询.
     *
     * 参数说明：
     * - company_name (必填) 企业名称（需准确；81api 通道同时支持信用代码）
     *
     * @return array{driver: string, company: null|array}
     */
    public function detail(array $params): array
    {
        $companyName = (string) ($params['company_name'] ?? '');

        return $this->run('detail', ['company_name' => $companyName], function () use ($companyName): array {
            $manager = new CompanyVerifyManager();
            $provider = $manager->driverFor('detail');
            $info = $provider->detail($companyName);

            LogHelper::info('company detail result', [
                'company_name' => $companyName,
                'driver' => $provider->name(),
                'found' => $info !== null,
            ], 'company_verify');

            return [
                'driver' => $provider->name(),
                'company' => $info !== null ? $this->formatCompany($info) : null,
            ];
        });
    }

    /**
     * 统一异常边界：三方/配置异常与意外错误都记日志后转为业务异常.
     *
     * @param string $action 动作标识（verify/search/detail），用于日志
     * @param array $context 日志上下文（入参）
     * @param callable $callback 实际业务逻辑
     */
    private function run(string $action, array $context, callable $callback): array
    {
        try {
            return $callback();
        } catch (CompanyVerifyException $e) {
            LogHelper::error("company {$action} failed", $context + [
                'message' => $e->getMessage(),
            ], 'company_verify');
            throw new BusinessException(CommonCode::OPERATION_FAILED);
        } catch (Throwable $e) {
            LogHelper::error("company {$action} unexpected error", $context + [
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ], 'company_verify');
            throw new BusinessException(CommonCode::OPERATION_FAILED);
        }
    }

    /**
     * @param CompanyInfo[] $companies
     */
    private function findMatchedCompany(array $companies, string $companyName, ?string $creditCode): ?CompanyInfo
    {
        foreach ($companies as $item) {
            if (! $this->equalsName($item->name, $companyName)) {
                continue;
            }
            if ($creditCode !== null && ! $this->equalsCode($item->creditCode, $creditCode)) {
                continue;
            }

            return $item;
        }

        return null;
    }

    private function formatCompany(CompanyInfo $info): array
    {
        return [
            'name' => $info->name,
            'credit_code' => $info->creditCode,
            'legal_person_name' => $info->legalPersonName,
            'reg_status' => $info->regStatus,
            'reg_capital' => $info->regCapital,
            'reg_number' => $info->regNumber,
            'establish_time' => $info->estiblishTime,
            'reg_location' => $info->regLocation,
            'business_scope' => $info->businessScope,
            'company_org_type' => $info->companyOrgType,
            'reg_institute' => $info->regInstitute,
            'org_number' => $info->orgNumber,
            'actual_capital' => $info->actualCapital,
            'reg_capital_currency' => $info->regCapitalCurrency,
            'industry' => $info->industry,
            'social_staff_num' => $info->socialStaffNum,
            'business_term' => $info->businessTerm,
        ];
    }

    private function equalsCode(?string $a, string $b): bool
    {
        return $a !== null && strcasecmp(trim($a), trim($b)) === 0;
    }

    private function equalsName(?string $a, string $b): bool
    {
        if ($a === null) {
            return false;
        }

        return $this->normalizeName($a) === $this->normalizeName($b);
    }

    private function normalizeName(string $name): string
    {
        $name = trim($name);

        return str_replace(['（', '）'], ['(', ')'], $name);
    }
}

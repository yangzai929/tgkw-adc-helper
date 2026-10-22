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
 * 三方通道由服务端配置决定，入参不支持指定：优先使用操作级
 * company_verify_{verify|search|detail}_driver；为空时回退到默认
 * systemConfig.company_verify_driver。
 */
class CompanyVerifyService
{
    /**
     * 允许测试或非 DI 场景注入 Manager；生产环境按需创建默认实例。
     */
    public function __construct(private ?CompanyVerifyManager $manager = null)
    {
    }

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
            $provider = $this->manager()->driverFor('verify');
            $company = $this->findCompanyByName($provider->search($companyName), $companyName);

            // 仅在名称完全未命中时，才使用信用代码补偿检索。
            // 名称已命中但信用代码不一致属于正常的核验不通过，不能再将错误信用代码
            // 发送给上游 search 接口，否则上游错误会被误转为「操作失败」。
            if ($company === null && $creditCode !== null) {
                $company = $this->findCompanyByName($provider->search($creditCode), $companyName);
            }

            $matched = $company !== null
                && ($creditCode === null || $this->equalsCode($company->creditCode, $creditCode))
                ? $company
                : null;
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
            $provider = $this->manager()->driverFor('search');
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
            $provider = $this->manager()->driverFor('detail');
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

    private function manager(): CompanyVerifyManager
    {
        return $this->manager ??= new CompanyVerifyManager();
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
     * 从候选企业中查找名称完全匹配项。
     *
     * 名称匹配与信用代码匹配必须分开处理：前者用于决定是否需要补偿检索，
     * 后者只决定本次核验是否通过。
     *
     * @param CompanyInfo[] $companies
     */
    private function findCompanyByName(array $companies, string $companyName): ?CompanyInfo
    {
        foreach ($companies as $item) {
            if ($this->equalsName($item->name, $companyName)) {
                return $item;
            }
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

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

        try {
            $manager = new CompanyVerifyManager();
            $provider = $manager->driver();
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
        } catch (CompanyVerifyException $e) {
            LogHelper::error('company verify failed', [
                'company_name' => $companyName,
                'credit_code' => $creditCode,
                'message' => $e->getMessage(),
            ], 'company_verify');
            throw new BusinessException(CommonCode::OPERATION_FAILED);
        } catch (Throwable $e) {
            LogHelper::error('company verify unexpected error', [
                'company_name' => $companyName,
                'credit_code' => $creditCode,
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

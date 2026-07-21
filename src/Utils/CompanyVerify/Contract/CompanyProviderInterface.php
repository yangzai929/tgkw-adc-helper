<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Utils\CompanyVerify\Contract;

use TgkwAdc\Utils\CompanyVerify\DTO\CompanyInfo;

/**
 * 企业信息查询三方服务统一契约.
 *
 * 每个三方（天眼查、数脉数据等）实现本接口，向上层提供一致的查询能力。
 * 业务系统只面向本接口与 {@see CompanyInfo}，无需关注底层用的是哪个三方。
 */
interface CompanyProviderInterface
{
    /**
     * 三方标识（如：tianyancha、shumai），用于配置选择与结果标注.
     */
    public function name(): string;

    /**
     * 校验企业名称（及可选信用代码）是否真实存在.
     *
     * @param string $companyName 企业名称
     * @param null|string $creditCode 统一社会信用代码，传入时一并比对
     * @return bool 是否真实且匹配
     */
    public function verify(string $companyName, ?string $creditCode = null): bool;

    /**
     * 模糊搜索企业，返回候选列表.
     *
     * @param string $keyword 搜索关键词
     * @return CompanyInfo[] 候选企业列表，无结果时返回空数组
     */
    public function search(string $keyword): array;

    /**
     * 获取单个企业的完整工商信息.
     *
     * @param string $companyName 企业名称（需准确）
     * @return null|CompanyInfo 未查询到时返回 null
     */
    public function detail(string $companyName): ?CompanyInfo;
}

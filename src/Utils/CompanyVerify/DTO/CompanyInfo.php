<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Utils\CompanyVerify\DTO;

/**
 * 标准企业信息对象.
 *
 * 不同三方返回的字段结构差异很大，统一映射到本对象，业务系统只需面向这些字段，
 * 无需关注底层数据来源。原始响应保留在 {@see CompanyInfo::$raw} 中以备特殊场景使用。
 */
class CompanyInfo
{
    /**
     * @param string $name 企业名称
     * @param null|string $creditCode 统一社会信用代码
     * @param null|string $legalPersonName 法定代表人
     * @param null|string $regStatus 登记状态（如：存续、注销）
     * @param null|string $regCapital 注册资本
     * @param null|string $regNumber 工商注册号
     * @param null|string $estiblishTime 成立日期
     * @param null|string $regLocation 注册地址
     * @param null|string $businessScope 经营范围
     * @param string $provider 数据来源标识（如：tianyancha、shumai）
     * @param array $raw 三方原始返回数据
     */
    public function __construct(
        public string $name = '',
        public ?string $creditCode = null,
        public ?string $legalPersonName = null,
        public ?string $regStatus = null,
        public ?string $regCapital = null,
        public ?string $regNumber = null,
        public ?string $estiblishTime = null,
        public ?string $regLocation = null,
        public ?string $businessScope = null,
        public string $provider = '',
        public array $raw = [],
    ) {
    }

    /**
     * 从映射好的字段数组构建对象.
     *
     * @param array $data 已按标准字段命名的数组
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) ($data['name'] ?? ''),
            creditCode: self::nullableString($data['creditCode'] ?? null),
            legalPersonName: self::nullableString($data['legalPersonName'] ?? null),
            regStatus: self::nullableString($data['regStatus'] ?? null),
            regCapital: self::nullableString($data['regCapital'] ?? null),
            regNumber: self::nullableString($data['regNumber'] ?? null),
            estiblishTime: self::nullableString($data['estiblishTime'] ?? null),
            regLocation: self::nullableString($data['regLocation'] ?? null),
            businessScope: self::nullableString($data['businessScope'] ?? null),
            provider: (string) ($data['provider'] ?? ''),
            raw: (array) ($data['raw'] ?? []),
        );
    }

    /**
     * 转为数组（含原始数据）.
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'creditCode' => $this->creditCode,
            'legalPersonName' => $this->legalPersonName,
            'regStatus' => $this->regStatus,
            'regCapital' => $this->regCapital,
            'regNumber' => $this->regNumber,
            'estiblishTime' => $this->estiblishTime,
            'regLocation' => $this->regLocation,
            'businessScope' => $this->businessScope,
            'provider' => $this->provider,
            'raw' => $this->raw,
        ];
    }

    private static function nullableString($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}

<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Helper\CompanyVerify\DTO;

/**
 * 标准企业信息对象.
 *
 * 字段取各自三方（天眼查 / 81api / 极速数据）都能提供的数据交集，统一映射到本对象，
 * 业务系统只需面向这些字段，无需关注底层数据来源。
 * 原始响应保留在 {@see CompanyInfo::$raw} 中以备特殊场景使用。
 *
 * 标准字段与三方返回的对应关系：
 *
 * | 标准字段           | 天眼查                 | 81api                                | 极速数据(jisu)        |
 * |--------------------|------------------------|--------------------------------------|-----------------------|
 * | name               | name                   | name                                 | name                  |
 * | creditCode         | creditCode             | registerData.creditNo                | creditno              |
 * | legalPersonName    | legalPersonName        | legalPersonName                      | legalperson           |
 * | regStatus          | regStatus              | registerData.status                  | status                |
 * | regCapital         | regCapital             | registerCapital                      | regcapital            |
 * | regNumber          | regNumber              | registerData.registerNo              | regno                 |
 * | estiblishTime      | estiblishTime(毫秒)    | startDate                            | startdate             |
 * | regLocation        | regLocation            | registerData.address                 | regaddress            |
 * | businessScope      | businessScope          | registerData.businessScope           | scope                 |
 * | companyOrgType     | companyOrgType         | registerData.regType                 | type                  |
 * | regInstitute       | regInstitute           | registerData.belongOrg               | regorgan              |
 * | orgNumber          | orgNumber              | registerData.orgNo                   | orgno                 |
 * | actualCapital      | actualCapital          | registerData.actualCapital(可定制)   | reccap                |
 * | regCapitalCurrency | regCapitalCurrency     | registerData.registerCapitalCurrency | regcapitalcur         |
 * | industry           | industry               | registerData.industry(可定制)        | nicname               |
 * | socialStaffNum     | socialStaffNum         | socialEmployeeNum(可定制)            | insurenum             |
 * | businessTerm       | fromTime/toTime 拼接   | registerData.businessTerm            | startdate/enddate拼接 |
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
     * @param null|string $estiblishTime 成立日期（Y-m-d）
     * @param null|string $regLocation 注册地址
     * @param null|string $businessScope 经营范围
     * @param null|string $companyOrgType 公司类型
     * @param null|string $regInstitute 登记机关
     * @param null|string $orgNumber 组织机构代码
     * @param null|string $actualCapital 实缴注册资本
     * @param null|string $regCapitalCurrency 注册资本币种
     * @param null|string $industry 所属行业
     * @param null|string $socialStaffNum 参保人数
     * @param null|string $businessTerm 营业期限
     * @param string $provider 数据来源标识（如：tianyancha、81api）
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
        public ?string $companyOrgType = null,
        public ?string $regInstitute = null,
        public ?string $orgNumber = null,
        public ?string $actualCapital = null,
        public ?string $regCapitalCurrency = null,
        public ?string $industry = null,
        public ?string $socialStaffNum = null,
        public ?string $businessTerm = null,
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
            companyOrgType: self::nullableString($data['companyOrgType'] ?? null),
            regInstitute: self::nullableString($data['regInstitute'] ?? null),
            orgNumber: self::nullableString($data['orgNumber'] ?? null),
            actualCapital: self::nullableString($data['actualCapital'] ?? null),
            regCapitalCurrency: self::nullableString($data['regCapitalCurrency'] ?? null),
            industry: self::nullableString($data['industry'] ?? null),
            socialStaffNum: self::nullableString($data['socialStaffNum'] ?? null),
            businessTerm: self::nullableString($data['businessTerm'] ?? null),
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
            'companyOrgType' => $this->companyOrgType,
            'regInstitute' => $this->regInstitute,
            'orgNumber' => $this->orgNumber,
            'actualCapital' => $this->actualCapital,
            'regCapitalCurrency' => $this->regCapitalCurrency,
            'industry' => $this->industry,
            'socialStaffNum' => $this->socialStaffNum,
            'businessTerm' => $this->businessTerm,
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

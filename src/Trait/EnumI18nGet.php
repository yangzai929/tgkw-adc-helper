<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Trait;

use ReflectionEnum;
use ReflectionEnumUnitCase;
use TgkwAdc\Annotation\EnumI18n;
use TgkwAdc\Annotation\EnumI18nGroup;
use TgkwAdc\Helper\EnumStore;
use TgkwAdc\Helper\Intl\I18nHelper;

trait EnumI18nGet
{
    /**
     * 获取枚举所属分组编码。
     *
     * @return array{groupCode:null|int}
     */
    public static function getEnumsGroupCode(): array
    {
        $res = self::getEnumClassAttitude();

        return [
            'groupCode' => $res->groupCode ?? null,
        ];
    }

    /**
     * 获取当前枚举类的全部定义数组（含 i18n 等）。
     */
    public function getTxtArr(): ?array
    {
        return self::getEnums();
    }

    /**
     * 获取当前枚举项的文本。
     */
    public function getTxt(): ?string
    {
        return self::getEnums()[$this->name]['txt'] ?? null;
    }

    /**
     * 转换当前枚举项为数组表示。
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'value' => $this->value,
            'txt' => $this->getTxt(),
            'i18nTxt' => $this->getI18nTxt(),
            'group' => [
                'groupCode' => $this->getI18nGroupCode(),
            ],
        ];
    }

    /**
     * 获取当前枚举项的 i18n 文本。
     */
    public function getI18nTxt(?string $key = null): array|string|null
    {
        if ($key !== null) {
            return self::getEnums()[$this->name]['i18nTxt'][$key] ?? null;
        }

        return self::getEnums()[$this->name]['i18nTxt'] ?? null;
    }

    /**
     * 生成可返回的 i18n 数据或当前语言文本。
     *
     * @param array $i18nParams 占位符参数，形如 ['name' => 'Tom']
     * @param bool $returnNowLang 为 true 时仅返回当前语言文本
     * @param string $language 指定语言，留空则取当前语言
     */
    public function genI18nTxt(array $i18nParams = [], bool $returnNowLang = false, string $language = ''): array|string
    {
        $txtArr = self::getEnums()[$this->name];

        if ($returnNowLang) {
            $nowLang = I18nHelper::getNowLang($language);
            $txt = $txtArr['i18nTxt'][$nowLang] ?? $txtArr['txt'];
            foreach ($i18nParams as $key => $value) {
                $txt = str_replace(sprintf('{%s}', $key), (string) $value, $txt);
            }

            return $txt;
        }

        return [
            'value' => $txtArr['txt'],
            'i18n_value' => $txtArr['i18nTxt'],
            'i18n_key' => $txtArr['i18nKey'],
            'i18n_params' => $i18nParams,
        ];
    }

    /**
     * 获取当前枚举项所属分组编码。
     */
    public function getI18nGroupCode(): ?int
    {
        return self::getEnums()[$this->name]['group']['groupCode'] ?? null;
    }

    /**
     * 获取当前枚举类的定义集合。
     * - 当 $onlyCode 为 true：仅从代码注解构建数组，不写入缓存
     * - 默认：构建后写入内存缓存，后续复用.
     * @param mixed $onlyCode
     */
    public static function getEnums($onlyCode = false): array
    {
        $enum = new ReflectionEnum(static::class);
        $enumClassName = $enum->getName();
        if (EnumStore::isset($enumClassName)) {
            return EnumStore::get($enumClassName);
        }
        $enumCases = $enum->getCases();
        $classObj = self::getEnumClassAttitude();

        // i18n 来源：统一使用代码内注解定义
        $langList = [];

        $caseAll = [];
        $appName = env('APP_NAME');
        $groupCode = $classObj->groupCode;
        foreach ($enumCases as $enumCase) {
            /** @var self $case */
            $case = $enumCase->getValue();
            $obj = $case->getEnumCase();

            $caseValue = $case->value;
            $caseArr = [
                'name' => $case->name,
                'value' => $caseValue,
                'txt' => $obj->txt,
                'i18nTxt' => $langList[$caseValue] ?? $obj->i18nTxt,
                'group' => [
                    'groupCode' => $groupCode,
                ],
            ];
            $caseArr['i18nKey'] = 'i18n.' . $appName . '.' . $groupCode . '.' . $caseValue;

            $caseAll[$case->name] = $caseArr;
        }

        // 仅代码构建：不写入缓存
        if ($onlyCode) {
            return $caseAll;
        }

        EnumStore::setAll($enumClassName, $caseAll);

        return EnumStore::get($enumClassName);
    }

    /**
     * 获取枚举类注解信息（分组）。
     */
    protected static function getEnumClassAttitude(): ?EnumI18nGroup
    {
        return (new ReflectionEnum(static::class))->getAttributes(EnumI18nGroup::class)[0]->newInstance() ?? null;
    }

    /**
     * 获取当前枚举项的注解信息（文本、i18n）。
     */
    protected function getEnumCase(): ?EnumI18n
    {
        return (new ReflectionEnumUnitCase($this, $this->name))->getAttributes(EnumI18n::class)[0]->newInstance() ?? null;
    }
}

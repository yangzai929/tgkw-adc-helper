<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Helper;

use Carbon\Carbon;
use Exception;
use ReflectionClass;
use TgkwAdc\Helper\Intl\I18nHelper;
use TgkwAdc\Office\Annotation\ExcelProperty;
use Vtiful\Kernel\Excel;

/**
 * Excel写入助手类
 * 提供Excel数据处理的相关功能，包括日期格式化、字段配置构建等.
 */
class XlsWriterHelper
{
    /**
     * Excel属性元数据缓存
     * 用于存储DTO类的Excel属性注解信息，避免重复反射操作.
     *
     * @var array<class-string, array<string, ExcelProperty>>
     */
    private array $excelPropertyMetas = [];

    /**
     * 文件夹名称.
     */
    private string $folder = 'viest';

    /**
     * 设置文件夹名称.
     *
     * @param string $folder 文件夹名称
     * @return $this
     */
    public function setFolder(string $folder): self
    {
        $this->folder = $folder;
        return $this;
    }

    /**
     * 初始化并返回Excel对象
     */
    public function init(): Excel
    {
        $config = [
            'path' => BASE_PATH . '/runtime/xls/' . $this->folder, // xlsx文件保存路径
        ];

        // 创建文件路径
        $this->makeDir($config['path']);

        return new Excel($config);
    }

    /**
     * 格式化日期时间
     * 根据类型将Excel中的日期时间字符串转换为标准格式.
     *
     * @param string $value 日期时间字符串
     * @param string $dateTimeType 类型：date(日期)、time(时间)、dateTime(日期时间)
     * @return string 格式化后的日期时间字符串，失败返回空字符串
     */
    public function formatDate(string $value, string $dateTimeType): string
    {
        // 如果输入值为空，直接返回空字符串
        if (empty($value)) {
            return '';
        }

        $carbon = null;

        // 尝试解析为时间戳（兼容数字字符串）
        if (is_numeric($value)) {
            $timestamp = (float) $value;
            // Excel日期序列号通常大于25569（1900-01-01的Unix时间戳）
            // 如果数字很大，可能是Excel序列号（从1900-01-01开始的天数）
            if ($timestamp > 25569 && $timestamp < 1000000) {
                // Excel序列号转Unix时间戳：Excel从1900-01-01开始，需要减去2天（Excel的1900年闰年bug）
                $unixTimestamp = (int) (($timestamp - 25569) * 86400);
                try {
                    $carbon = Carbon::createFromTimestamp($unixTimestamp);
                } catch (Exception $e) {
                    // 如果转换失败，尝试作为普通时间戳
                    try {
                        $carbon = Carbon::createFromTimestamp((int) $timestamp);
                    } catch (Exception $e) {
                        return '';
                    }
                }
            } else {
                // 直接作为Unix时间戳处理
                try {
                    $carbon = Carbon::createFromTimestamp((int) $timestamp);
                } catch (Exception $e) {
                    return '';
                }
            }
        } else {
            // 尝试使用Carbon解析各种日期格式
            try {
                $carbon = Carbon::parse($value);
            } catch (Exception $e) {
                // 尝试常见的中文日期格式
                $normalizedValue = $this->normalizeChineseDate($value);
                try {
                    $carbon = Carbon::parse($normalizedValue);
                } catch (Exception $e) {
                    return '';
                }
            }
        }

        // 如果无法创建Carbon实例，返回空字符串
        if ($carbon === null) {
            return '';
        }

        // 根据类型返回对应格式
        return match ($dateTimeType) {
            'date' => $carbon->format('Y-m-d'),
            'time' => $carbon->format('H:i:s'),
            'dateTime' => $carbon->format('Y-m-d H:i:s'),
            default => $carbon->format('Y-m-d H:i:s'),
        };
    }

    /**
     * 构建额外信息数组
     * 根据字段配置和DTO类生成额外的信息，用于Excel模板
     *
     * @param array<string, array|string> $fieldConfigs 字段配置数组
     * @param string $dtoClass DTO类名
     * @return array 额外信息数组
     */
    public function buildExtraInfo(array $fieldConfigs, string $dtoClass): array
    {
        // 如果字段配置为空，直接返回空数组
        if ($fieldConfigs === []) {
            return [];
        }

        $extraInfo = [];
        foreach ($fieldConfigs as $field => $config) {
            // 如果配置是字符串，则将其转换为数组格式
            if (is_string($config)) {
                $config = ['enum' => $config];
            }
            // 如果没有枚举配置，跳过该字段
            if (! isset($config['enum'])) {
                continue;
            }

            // 获取属性键和标签键
            $propertyKey = $config['property'] ?? $field;
            $labelKey = $config['label_field'] ?? $propertyKey;

            // 获取属性元数据
            $property = $this->getExcelPropertyMeta($propertyKey, $dtoClass);
            $labelProperty = $labelKey === $propertyKey ? $property : $this->getExcelPropertyMeta($labelKey, $dtoClass);

            // 构建字典数据
            $dictData = $this->buildDictDataFromEnum($config['enum']);

            // 添加到额外信息数组
            $extraInfo[] = [
                'fields_name' => $this->resolveFieldLabel($labelProperty, $labelKey),
                'fill' => $property?->required ?? true,
                'key' => $field,
                'dictData' => $dictData,
            ];
        }

        return $extraInfo;
    }

    /**
     * 构建模板提示信息
     * 根据DTO类生成Excel模板的提示信息.
     *
     * @param string $dtoClass DTO类名
     * @return array 模板提示信息数组
     */
    public function buildTemplateTips(string $dtoClass): array
    {
        $tips = [];
        // 遍历DTO类的所有Excel属性元数据
        foreach ($this->getExcelPropertyMetas($dtoClass) as $property) {
            // 解析属性提示文本
            $tipText = $this->resolvePropertyTip($property);
            // 如果提示文本为空，跳过该属性
            if ($tipText === '') {
                continue;
            }
            // 添加到提示数组
            $tips[] = [
                'value' => $this->resolvePropertyLabel($property),
                'tip' => $tipText,
            ];
        }

        return $tips;
    }

    /**
     * 从枚举类构建字典数据.
     *
     * @param class-string $enumClass 枚举类名
     * @return array 字典数据数组
     */
    public function buildDictDataFromEnum(string $enumClass): array
    {
        $dictData = [];
        // 遍历枚举类的所有情况
        foreach ($enumClass::cases() as $case) {
            // 将枚举值作为键，国际化文本作为值
            $dictData[(string) $case->value] = $this->translate($case->getI18nTxt());
        }

        return $dictData;
    }

    /**
     * 规范化中文日期格式
     * 将中文日期格式转换为标准格式.
     *
     * @param string $value 日期字符串
     * @return string 规范化后的日期字符串
     */
    protected function normalizeChineseDate(string $value): string
    {
        // 替换常见的中文日期格式
        $value = str_replace(['年', '月', '日', '时', '分', '秒'], ['-', '-', ' ', ':', ':', ''], $value);
        $value = preg_replace('/\s+/', ' ', $value);
        return trim($value);
    }

    /**
     * 获取Excel属性元数据
     * 使用反射获取DTO类的所有ExcelProperty注解信息.
     *
     * @param string $dtoClass DTO类名
     * @return array<string, ExcelProperty> 属性元数据数组
     */
    protected function getExcelPropertyMetas(string $dtoClass): array
    {
        // 如果已缓存该类的元数据，直接返回
        if (! empty($this->excelPropertyMetas[$dtoClass])) {
            return $this->excelPropertyMetas[$dtoClass];
        }

        // 使用反射获取类的所有属性
        $reflection = new ReflectionClass($dtoClass);
        foreach ($reflection->getProperties() as $property) {
            // 获取属性上的ExcelProperty注解
            $attributes = $property->getAttributes(ExcelProperty::class);
            // 如果没有ExcelProperty注解，跳过该属性
            if (empty($attributes)) {
                continue;
            }
            /** @var ExcelProperty $instance */
            $instance = $attributes[0]->newInstance();
            // 将注解实例存储到缓存中
            $this->excelPropertyMetas[$dtoClass][$property->getName()] = $instance;
        }

        return $this->excelPropertyMetas[$dtoClass] ?? [];
    }

    /**
     * 获取指定名称的Excel属性元数据.
     *
     * @param string $name 属性名称
     * @param string $dtoClass DTO类名
     * @return null|ExcelProperty Excel属性元数据对象或null
     */
    protected function getExcelPropertyMeta(string $name, string $dtoClass): ?ExcelProperty
    {
        $metas = $this->getExcelPropertyMetas($dtoClass);
        return $metas[$name] ?? null;
    }

    /**
     * 解析属性标签
     * 根据当前语言环境获取属性的国际化标签.
     *
     * @param ExcelProperty $property Excel属性对象
     * @return string 属性标签
     */
    protected function resolvePropertyLabel(ExcelProperty $property): string
    {
        $lang = $this->getNowLang();
        return $property->i18nValue[$lang] ?? $property->value;
    }

    /**
     * 解析属性提示信息
     * 根据当前语言环境获取属性的国际化提示信息.
     *
     * @param ExcelProperty $property Excel属性对象
     * @return string 属性提示信息
     */
    protected function resolvePropertyTip(ExcelProperty $property): string
    {
        $lang = $this->getNowLang();
        return $property->i18nTip[$lang] ?? $property->tip ?? '';
    }

    /**
     * 解析字段标签
     * 根据属性元数据或回退键名获取字段标签.
     *
     * @param null|ExcelProperty $property Excel属性对象或null
     * @param string $fallbackKey 回退键名
     * @return string 字段标签
     */
    protected function resolveFieldLabel(?ExcelProperty $property, string $fallbackKey): string
    {
        // 如果属性存在，使用属性标签
        if ($property !== null) {
            return $this->resolvePropertyLabel($property);
        }

        // 否则将键名人性化处理后作为标签
        return $this->humanizeFieldKey($fallbackKey);
    }

    /**
     * 人性化字段键名
     * 将下划线或连字符分隔的键名转换为首字母大写的驼峰命名.
     *
     * @param string $key 字段键名
     * @return string 人性化的字段名称
     */
    protected function humanizeFieldKey(string $key): string
    {
        $humanized = ucfirst(str_replace(['_', '-'], ' ', $key));
        return str_replace(' ', '', $humanized);
    }

    /**
     * 翻译文本
     * 根据当前语言环境翻译文本数组.
     *
     * @param array $translations 多语言翻译数组
     * @param string $fallback 回退文本
     * @return string 翻译后的文本
     */
    protected function translate(array $translations, string $fallback = ''): string
    {
        $lang = $this->getNowLang();
        $fallback = $fallback === '' ? (reset($translations) ?: '') : $fallback;
        return $translations[$lang] ?? $fallback;
    }

    /**
     * 获取当前语言环境.
     *
     * @return string 当前语言代码
     */
    protected function getNowLang(): string
    {
        return I18nHelper::getNowLang();
    }

    /**
     * 创建文件路径.
     * @param string $path 文件路径
     */
    private function makeDir(string $path): void
    {
        // 判断目录存在否，存在给出提示，不存在则创建目录
        if (is_dir($path)) {
            // 目录已存在
        } else {
            // 第三个参数是“true”表示能创建多级目录
            mkdir($path, 0777, true);
        }
    }
}

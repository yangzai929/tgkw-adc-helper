<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Office\Annotation;

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;

/**
 * Excel属性注解类.
 *
 * 该注解用于标记类的属性，定义该属性在Excel导入导出时的行为和样式。
 * 可以配置列名、示例数据、提示信息、样式等属性。
 *
 * 使用示例：
 * #[ExcelProperty("用户名", 0, demo: "张三", tip: "请输入用户真实姓名")]
 * public string $username;
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class ExcelProperty extends AbstractAnnotation
{
    /**
     * 列表头名称.
     */
    public string $value;

    /**
     * 列表头名称（国际化）.
     */
    public array $i18nValue;

    /**
     * 示例数据.
     */
    public string $demo;

    /**
     * 示例数据（国际化）.
     */
    public array $i18nDemo;

    /**
     * 字段提示.
     */
    public string $tip;

    /**
     * 字段提示（国际化）.
     */
    public array $i18nTip;

    /**
     * 列顺序.
     */
    public int $index;

    /**
     * 宽度.
     */
    public int $width;

    /**
     * 高度（仅取第一个的）.
     */
    public int $height;

    /**
     * 对齐方式，默认居左.
     */
    public string $align;

    /**
     * 列表头是否必填.
     */
    public bool $required;

    /**
     * 列表头的高度（仅取第一个的）.
     */
    public int $headHeight;

    /**
     * 列表体字体颜色.
     */
    public int|string $color;

    /**
     * 列表体背景颜色.
     */
    public int|string $bgColor;

    /**
     * 字典数组（例如公司列表等情况使用）.
     */
    public array $dictData;

    /**
     * 字典名单（将自动前往public服务查找字典内容，自行引入public服务）.
     */
    public string $dictName;

    /**
     * 数据路径 用法: object.value.
     */
    public string $path;

    /**
     * 是否是日期时间格式，日期填写date，时间填写time，日期时间填写dateTime.
     */
    public string $dateTime;

    /**
     * 构造函数.
     *
     * @param string $value 列表头名称
     * @param int $index 列顺序
     * @param string $demo 示例数据
     * @param string $tip 字段提示
     * @param array $i18nValue 列表头名称（国际化）
     * @param array $i18nDemo 示例数据（国际化）
     * @param array $i18nTip 字段提示（国际化）
     * @param null|int $width 宽度
     * @param null|int $height 高度
     * @param null|string $align 对齐方式
     * @param bool $required 是否必填
     * @param null|int $headHeight 列表头高度
     * @param null|int|string $color 字体颜色
     * @param null|int|string $bgColor 背景颜色
     * @param string $dictName 字典名称
     * @param array $dictData 字典数据
     * @param null|string $path 数据路径
     * @param string $dateTime 日期时间格式
     */
    public function __construct(
        string $value,
        int $index,
        string $demo = '',
        string $tip = '',
        array $i18nValue = [],
        array $i18nDemo = [],
        array $i18nTip = [],
        ?int $width = null,
        ?int $height = null,
        ?string $align = null,
        bool $required = false,
        ?int $headHeight = null,
        int|string|null $color = null,
        int|string|null $bgColor = null,
        string $dictName = '',
        array $dictData = [],
        ?string $path = null,
        string $dateTime = '',
    ) {
        $this->value = $value;
        $this->index = $index;
        $this->required = $required;
        isset($demo) && $this->demo = $demo;
        isset($tip) && $this->tip = $tip;
        isset($i18nValue) && $this->i18nValue = $i18nValue;
        isset($i18nDemo) && $this->i18nDemo = $i18nDemo;
        isset($i18nTip) && $this->i18nTip = $i18nTip;
        isset($width) && $this->width = $width;
        isset($height) && $this->height = $height;
        isset($align) && $this->align = $align;
        isset($headHeight) && $this->headHeight = $headHeight;
        isset($color) && $this->color = $color;
        isset($bgColor) && $this->bgColor = $bgColor;
        isset($dictName) && $this->dictName = $dictName;
        isset($dictData) && $this->dictData = $dictData;
        isset($path) && $this->path = $path;
        isset($dateTime) && $this->dateTime = $dateTime;
    }
}

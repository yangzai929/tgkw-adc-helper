<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Annotation;

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;

/**
 * 国际化文本注解.
 *
 * 用于枚举常量的国际化文本定义，支持多语言文本和占位符替换
 *
 * @example
 * #[EnumI18n(
 *     txt: '用户不存在',
 *     i18nTxt: [
 *         'en' => 'User does not exist',
 *         'zh_hk' => '用戶不存在',
 *         'zh_tw' => '用戶不存在',
 *         'ja' => 'ユーザーが存在しません'
 *     ]
 * )]
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
class EnumI18n extends AbstractAnnotation
{
    /**
     * 中文内容.
     */
    public string $txt = '';

    /**
     * 国际化内容.
     *
     * 格式: ['en' => 'English text', 'zh_hk' => '繁體中文', ...]
     * 支持占位符: {placeholder_name}
     */
    public ?array $i18nTxt = null;

    /**
     * 构造函数.
     *
     * @param string $txt 中文内容，不能为空
     * @param null|array $i18nTxt 国际化内容数组，键为语言代码，值为对应文本
     */
    public function __construct(
        string $txt,
        ?array $i18nTxt = null,
    ) {
        $this->txt = $txt;
        $this->i18nTxt = $i18nTxt;
    }
}

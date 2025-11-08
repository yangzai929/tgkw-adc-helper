<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Constants;

class LocaleConstants
{
    /**
     * 支持的语言列表.
     */
    public const SUPPORTED_LOCALES = [
        'zh_cn' => 'zh_CN',
        'zh_CN' => 'zh_CN',
        'zh_hk' => 'zh_HK',
        'zh_HK' => 'zh_HK',
        'zh_TW' => 'zh_TW',
        'zh_tw' => 'zh_TW',
        'en' => 'en',
        'ja' => 'ja',
        'ko' => 'ko',
        'fr' => 'fr',
        'de' => 'de',
        'es' => 'es',
        'it' => 'it',
        'pt' => 'pt',
        'ru' => 'ru',
    ];

    /**
     * 语言显示名称.
     */
    public const LOCALE_NAMES = [
        'zh_cn' => '简体中文',
        'zh_CN' => '简体中文',
        'zh_HK' => '繁體中文(香港)',
        'zh_hk' => '繁體中文(香港)',
        'zh_TW' => '繁體中文(台灣)',
        'zh_tw' => '繁體中文(台灣)',
        'en' => 'English',
        'ja' => '日本語',
        'ko' => '한국어',
        'fr' => 'Français',
        'de' => 'Deutsch',
        'es' => 'Español',
        'it' => 'Italiano',
        'pt' => 'Português',
        'ru' => 'Русский',
    ];

    /**
     * 默认语言
     */
    public const DEFAULT_LOCALE = 'zh_cn';

    /**
     * 中文语言列表.
     */
    public const CHINESE_LOCALES = ['zh_cn', 'zh_hk', 'zh_tw'];

    /**
     * 获取支持的语言代码列表.
     */
    public static function getSupportedLocaleCodes(): array
    {
        return array_keys(self::SUPPORTED_LOCALES);
    }

    /**
     * 获取语言显示名称列表.
     */
    public static function getLocaleNames(): array
    {
        return self::LOCALE_NAMES;
    }

    /**
     * 检查语言代码是否支持
     */
    public static function isSupported(string $locale): bool
    {
        return isset(self::SUPPORTED_LOCALES[$locale]);
    }

    /**
     * 获取语言显示名称.
     */
    public static function getLocaleName(string $locale): string
    {
        return self::LOCALE_NAMES[$locale] ?? $locale;
    }

    /**
     * 检查是否为中文.
     */
    public static function isChinese(string $locale): bool
    {
        return in_array($locale, self::CHINESE_LOCALES);
    }

    /**
     * 获取默认语言
     */
    public static function getDefaultLocale(): string
    {
        return self::DEFAULT_LOCALE;
    }
}

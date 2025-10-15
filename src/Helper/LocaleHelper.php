<?php

declare(strict_types=1);

namespace TgkwAdc\Helper;

use TgkwAdc\Constants\LocaleConstants;
use Hyperf\Context\Context;
use Hyperf\Contract\TranslatorInterface;
use Hyperf\Context\ApplicationContext;

class LocaleHelper
{
    /**
     * 获取当前语言
     */
    public static function getCurrentLocale(): string
    {
        return Context::get('locale', LocaleConstants::getDefaultLocale());
    }

    /**
     * 设置当前语言
     */
    public static function setLocale(string $locale): void
    {
        Context::set('locale', $locale);

        // 同时设置翻译器的语言
        $container = ApplicationContext::getContainer();
        $translator = $container->get(TranslatorInterface::class);
        $translator->setLocale($locale);
    }

    /**
     * 翻译文本
     */
    public static function trans(string $key, array $replace = [], ?string $locale = null): string
    {
        $container = ApplicationContext::getContainer();
        $translator = $container->get(TranslatorInterface::class);

        $locale = $locale ?: self::getCurrentLocale();

        return $translator->trans($key, $replace, $locale);
    }

    /**
     * 检查是否为指定语言
     */
    public static function isLocale(string $locale): bool
    {
        return self::getCurrentLocale() === $locale;
    }

    /**
     * 检查是否为中文
     */
    public static function isChinese(): bool
    {
        $locale = self::getCurrentLocale();
        return LocaleConstants::isChinese($locale);
    }

    /**
     * 检查是否为英文
     */
    public static function isEnglish(): bool
    {
        return self::isLocale('en');
    }

    /**
     * 获取支持的语言列表
     */
    public static function getSupportedLocales(): array
    {
        return LocaleConstants::getLocaleNames();
    }

    /**
     * 获取语言显示名称
     */
    public static function getLocaleName(?string $locale = null): string
    {
        $locale = $locale ?: self::getCurrentLocale();
        return LocaleConstants::getLocaleName($locale);
    }
}

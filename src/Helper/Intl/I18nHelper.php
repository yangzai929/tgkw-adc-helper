<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Helper\Intl;

class I18nHelper
{
    protected static string $defaultLang = 'zh_CN';

    public static function getNowLang(string $overrideLang = ''): string
    {
        if ($overrideLang !== '') {
            return $overrideLang;
        }

        return $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? self::$defaultLang;
    }
}

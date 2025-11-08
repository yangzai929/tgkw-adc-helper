<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Helper\Intl;

use TgkwAdc\Helper\LocaleHelper;

class I18nHelper
{
    protected static string $defaultLang = 'zh_cn';

    public static function getNowLang(string $overrideLang = ''): string
    {
        if ($overrideLang !== '') {
            return $overrideLang;
        }

        return strtolower(LocaleHelper::getCurrentLocale()) ?? self::$defaultLang;
    }
}

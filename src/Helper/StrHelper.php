<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Helper;

class StrHelper
{
    /**
     * 多字节字符串两端修剪
     * 从字符串两端移除指定的字符.
     *
     * @param string $str 要修剪的字符串
     * @param string $char 要移除的字符，默认为空格
     * @param string $encoding 字符编码，默认为UTF-8
     * @return string 修剪后的字符串
     */
    public static function mb_trim(string $str, string $char = ' ', string $encoding = 'UTF-8'): string
    {
        if (empty($str)) {
            return $str;
        }

        if (empty($char)) {
            return $str;
        }

        $charLength = mb_strlen($char, $encoding);
        $strLength = mb_strlen($str, $encoding);

        // 从左侧移除
        $leftTrimmed = $str;
        while (mb_substr($leftTrimmed, 0, $charLength, $encoding) === $char) {
            $leftTrimmed = mb_substr($leftTrimmed, $charLength, null, $encoding);
        }

        // 从右侧移除
        $rightTrimmed = $leftTrimmed;
        $rightTrimmedLength = mb_strlen($rightTrimmed, $encoding);
        while ($rightTrimmedLength >= $charLength && mb_substr($rightTrimmed, -$charLength, null, $encoding) === $char) {
            $rightTrimmed = mb_substr($rightTrimmed, 0, $rightTrimmedLength - $charLength, $encoding);
            $rightTrimmedLength = mb_strlen($rightTrimmed, $encoding);
        }

        return $rightTrimmed;
    }
}

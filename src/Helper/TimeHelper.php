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

class TimeHelper
{
    private const MS_TIMESTAMP_THRESHOLD = 1e12;

    /** 解析为 Carbon */
    public static function toCarbon(mixed $value): Carbon
    {
        $val = (string) $value;
        if (is_numeric($val)) {
            $ts = (int) $val;
            $ts = $ts >= self::MS_TIMESTAMP_THRESHOLD ? (int) ($ts / 1000) : $ts;
            return Carbon::createFromTimestamp($ts);
        }
        return Carbon::parse($val);
    }

    /** 解析日期字符串或秒/毫秒时间戳为 Carbon */
    public static function parseDateOrTimestamp(mixed $value): Carbon
    {
        $val = (string) $value;
        if (is_numeric($val)) {
            $ts = (int) $val;
            $ts = $ts >= self::MS_TIMESTAMP_THRESHOLD ? (int) ($ts / 1000) : $ts;
            return Carbon::createFromTimestamp($ts);
        }
        return Carbon::parse($val);
    }

    /** 解析日期字符串或秒/毫秒时间戳为 Y-m-d 或Y-m-d H:i:s 或指定格式 */
    public static function parseDateOrTimestampTo(mixed $value, $format = 'Y-m-d'): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::parseDateOrTimestamp($value)->format($format);
    }
}

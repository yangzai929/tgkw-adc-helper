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

    /** 解析为 Carbon  （默认时区Asia/Shanghai）*/
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

    /** 解析日期字符串或秒/毫秒时间戳为 Carbon （默认时区Asia/Shanghai）*/
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

    /** 解析日期字符串或秒/毫秒时间戳为 Y-m-d 或Y-m-d H:i:s 或指定格式 默认时区Asia/Shanghai） */
    public static function parseDateOrTimestampTo(mixed $value, $format = 'Y-m-d'): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::parseDateOrTimestamp($value)->format($format);
    }

    /** 将 Y-m-d 视为北京时间日历日，当日 0 点转 UTC 的 ISO 8601，形如 2025-03-31T16:00:00.000000Z */
    public static function dateToIso8601(string $date): string
    {
        return Carbon::parse($date, 'Asia/Shanghai')
            ->startOfDay()
            ->utc()
            ->format('Y-m-d\TH:i:s.u\Z');
    }
}

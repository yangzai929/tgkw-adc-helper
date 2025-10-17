<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Helper;

class EnumStore
{
    protected static array $store = [];

    /**
     * 判断缓存是否存在。
     */
    public static function isset(string $enumClass, ?string $name = null): bool
    {
        if ($name === null) {
            return isset(self::$store[$enumClass]);
        }

        return isset(self::$store[$enumClass][$name]);
    }

    /**
     * 获取缓存内容。
     */
    public static function get(string $enumClass, ?string $name = null): mixed
    {
        if ($name === null) {
            return self::$store[$enumClass] ?? [];
        }

        return self::$store[$enumClass][$name] ?? null;
    }

    /**
     * 设置单个枚举项缓存。
     */
    public static function set(string $enumClass, string $name, array $data): void
    {
        self::$store[$enumClass][$name] = $data;
    }

    /**
     * 批量设置整个枚举类缓存。
     */
    public static function setAll(string $enumClass, array $data): void
    {
        self::$store[$enumClass] = $data;
    }

    /**
     * 清除缓存（全部或指定枚举类）。
     */
    public static function clear(string $enumClass = ''): void
    {
        if ($enumClass === '') {
            self::$store = [];
        } else {
            unset(self::$store[$enumClass]);
        }
    }
}

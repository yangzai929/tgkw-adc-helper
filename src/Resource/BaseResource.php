<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Resource;

use ArrayAccess;
use Carbon\Carbon;
use DateTime;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use JsonSerializable;
use TgkwAdc\Helper\Log\LogHelper;
use Throwable;

/**
 * 基础资源类
 * 提供统一的 API 响应格式和常用功能.
 */
abstract class BaseResource extends JsonResource
{
    private const SENSITIVE_FIELDS = [
        'password',
        'token',
        'secret',
        'access_key',
        'private_key',
    ];

    #[Inject]
    protected RequestInterface $request;

    /**
     * 输出数组数据
     * 自动隐藏敏感字段 + 异常保护.
     */
    public function toArray(): array
    {
        try {
            $data = parent::toArray();

            return $this->hideSensitiveFieldsRecursive($data);
        } catch (Throwable $e) {
            return [
                'error' => 'Resource serialization failed',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal Error',
            ];
        }
    }

    /**
     * 附加自定义数据.
     */
    public function withData(array $data): self
    {
        $this->additional = array_merge($this->additional ?? [], ['data_extra' => $data]);

        return $this;
    }

    /**
     * 附加元信息.
     */
    public function withMeta(array $meta): self
    {
        $this->additional = array_merge($this->additional ?? [], ['meta' => $meta]);

        return $this;
    }

    /**
     * 附加统计信息.
     */
    public function withStats(array $stats): self
    {
        $this->additional = array_merge($this->additional ?? [], ['stats' => $stats]);

        return $this;
    }

    /**
     * 递归隐藏敏感字段.
     */
    protected function hideSensitiveFieldsRecursive(array $data, array $fields = self::SENSITIVE_FIELDS): array
    {
        foreach ($data as $key => &$value) {
            if (in_array(strtolower($key), $fields, true)) {
                $value = '***';
            } elseif (is_array($value)) {
                $value = $this->hideSensitiveFieldsRecursive($value, $fields);
            } elseif ($value instanceof ArrayAccess || $value instanceof JsonSerializable) {
                $value = $this->hideSensitiveFieldsRecursive((array) $value, $fields);
            }
        }

        return $data;
    }

    /**
     * 日期格式化（安全）.
     * @param mixed $date
     */
    protected function formatDate($date, string $format = 'Y-m-d H:i:s'): ?string
    {
        // 1. 空值处理：空字符串/0/null 直接返回 null
        if (empty($date) && $date !== 0) {
            return null;
        }

        try {
            // 2. 处理 DateTime/Carbon 对象（原生兼容）
            if ($date instanceof DateTime || $date instanceof Carbon) {
                return $date->format($format);
            }

            // 3. 核心：处理数字类型的时间戳（秒级/毫秒级）
            if (is_numeric($date)) {
                $timestamp = (int) $date;

                // 判断是毫秒级（bigint，长度≥13位）还是秒级（int，长度10位）
                $isMillisecond = strlen((string) $timestamp) >= 13;

                // 用 Carbon 解析时间戳（Laravel 内置，兼容性更好）
                $carbon = $isMillisecond
                    ? Carbon::createFromTimestampMs($timestamp)  // 毫秒级
                    : Carbon::createFromTimestamp($timestamp);   // 秒级

                return $carbon->format($format);
            }

            // 4. 处理时间字符串（如 "2025-01-01 08:00:00"）
            $carbon = Carbon::parse($date);
            return $carbon->format($format);
        } catch (Throwable $exception) {
            // 5. 所有解析失败的情况都返回 null，避免程序崩溃
            LogHelper::info('ssssssssssssssssssss', [$exception->getMessage()]);
            return null;
        }
    }

    /**
     * 金额格式化.
     * @param mixed $amount
     */
    protected function formatMoney($amount, int $decimals = 2): string
    {
        return number_format((float) $amount, $decimals, '.', '');
    }

    /**
     * 获取枚举文本.
     * @param mixed $value
     */
    protected function getEnumText(string $enumClass, $value): ?string
    {
        if (! enum_exists($enumClass) || $value === null) {
            return null;
        }

        try {
            return $enumClass::from($value)->getText();
        } catch (Throwable) {
            return null;
        }
    }
}

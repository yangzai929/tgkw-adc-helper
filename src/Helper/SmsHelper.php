<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Helper;

use TgkwAdc\Annotation\EnumCodeInterface;
use TgkwAdc\Constants\Code\CommonCode;
use TgkwAdc\Exception\BusinessException;

/**
 * 短信验证码验证逻辑.
 *
 * 与 adc-messge 服务共用 Redis key 结构，其他服务可本地验证，无需 RPC 调用 message 服务。
 * Key: sms_code:{scene}:{token}, Value: {phone}|{code}
 */
class SmsHelper
{
    private const REDIS_PREFIX = 'sms_code:';

    /**
     * 生成 Redis key.
     */
    public static function tokenKey(string $scene, string $token): string
    {
        return self::REDIS_PREFIX . $scene . ':' . $token;
    }

    /**
     * 验证短信验证码，失败时抛出异常.
     *
     * @param string                                          $phone     手机号
     * @param string                                          $code      验证码
     * @param string                                          $token     发送时返回的 token
     * @param string                                          $scene     场景，默认 default
     * @param \TgkwAdc\Annotation\EnumCodeInterface|int|null  $errorCode 失败时的错误码
     */
    public static function verifyCodeOrThrow(
        string $phone,
        string $code,
        string $token,
        string $scene = 'default',
        EnumCodeInterface|int|null $errorCode = CommonCode::SMS_CODE_INVALID_OR_EXPIRED,
    ): void {
        if (! self::verifyCode($phone, $code, $token, $scene)) {
            throw new BusinessException($errorCode ?? 400);
        }
    }

    /**
     * 验证短信验证码.
     *
     * @param string $phone 手机号
     * @param string $code  验证码
     * @param string $token 发送时返回的 token
     * @param string $scene 场景，默认 default
     */
    public static function verifyCode(string $phone, string $code, string $token, string $scene = 'default'): bool
    {
        $key = self::tokenKey($scene, $token);
        $stored = redis()->get($key);
        if ($stored === false) {
            return false;
        }
        $parts = explode('|', $stored, 2);
        if (count($parts) !== 2 || $parts[0] !== $phone || $parts[1] !== $code) {
            return false;
        }
        redis()->del($key);

        return true;
    }
}

<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Helper;

class AesHelper
{
    /**
     * 加密.
     */
    public static function encrypt(string $plaintext): string
    {
        $cfg = self::getConfig();
        $encrypted = openssl_encrypt(
            $plaintext,
            $cfg['method'],
            $cfg['key'],
            OPENSSL_RAW_DATA,
            $cfg['iv']
        );

        return base64_encode($encrypted);
    }

    /**
     * 解密.
     */
    public static function decrypt(string $ciphertext): string
    {
        $cfg = self::getConfig();
        $decoded = base64_decode($ciphertext, true);

        return openssl_decrypt(
            $decoded,
            $cfg['method'],
            $cfg['key'],
            OPENSSL_RAW_DATA,
            $cfg['iv']
        );
    }

    /**
     * 获取配置.
     */
    protected static function getConfig(): array
    {
        $sysCfgJson = cfg('systemConfig');
        $systemCfg = json_decode($sysCfgJson, true);

        return [
            'method' => 'AES-256-CBC',
            'key' => $systemCfg['AES_128_KEY'],
            'iv' => 'abcdef1234567890',
        ];
    }
}

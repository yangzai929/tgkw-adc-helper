<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Helper;

use PHPUnit\Event\RuntimeException;

class RsaHelper
{
    public static function getPublicKey($keyPrefix = 'private_key', $expire = 60)
    {
        $keyPair = openssl_pkey_new(['private_key_bits' => 2048]);
        openssl_pkey_export($keyPair, $privateKey);
        $publicKeyDetails = openssl_pkey_get_details($keyPair);
        $publicKey = $publicKeyDetails['key'];
        $keyId = uniqid('key_', true);
        redis()->set($keyPrefix . $keyId, $privateKey, ['EX' => $expire]);

        return [
            'key_id' => $keyId,
            'public_key' => $publicKey,
            'expire' => $expire,
        ];
    }

    public static function encrypt($encryptedData, $privateCacheKeyId)
    {
        $encryptedPassword = hex2bin($encryptedData);
        $privateKey = redis()->get($privateCacheKeyId);
        if (! $privateKey) {
            throw new RuntimeException('私钥不存在');
        }

        $decryptedPassword = openssl_private_decrypt(
            $encryptedPassword,
            $decryptedData,
            $privateKey,
            OPENSSL_PKCS1_PADDING
        );

        if (! $decryptedPassword) {
            throw new RuntimeException('解密失败');
        }

        return $decryptedData;
    }
}

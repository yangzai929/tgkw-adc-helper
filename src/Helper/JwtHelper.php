<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Helper;

use Exception;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ServerRequestInterface;
use TgkwAdc\Constants\Code\TokenCode;
use TgkwAdc\Exception\TokenException;

class JwtHelper
{
    private static ?string $sys_key = null;

    private static ?string $org_key = null;

    private static ?string $alg = 'HS256';

    public static function init(): void
    {
        if (self::$sys_key == null) {
            $sysCfgJson = cfg('systemConfig');
            $systemCfg = json_decode($sysCfgJson, true);
            self::$sys_key = $systemCfg['JWT_SYSTEM_KEY'];
        }

        if (self::$org_key == null) {
            $sysCfgJson = cfg('systemConfig');
            $systemCfg = json_decode($sysCfgJson, true);
            self::$org_key = $systemCfg['JWT_ORG_KEY'];
        }
    }

    /**
     * 生成 Token.
     */
    public static function createToken(string $type = 'ORG', array $payload = [], int $ttl = 3600): string
    {
        self::init();
        $issuedAt = time();
        $expire = $issuedAt + $ttl;

        $tokenPayload = array_merge($payload, [
            'iat' => $issuedAt,
            'exp' => $expire,
        ]);

        return JWT::encode($tokenPayload, self::getKey($type), self::$alg);
    }

    /**
     * 解析 Token.
     */
    public static function parseToken(string $type = 'ORG', string $token = ''): array
    {
        try {
            self::init();

            return (array) JWT::decode($token, new Key(self::getKey($type), self::$alg));
        } catch (Exception $e) {
            if ($e instanceof ExpiredException) {
                throw new TokenException(TokenCode::EXPIRED_TOKEN);
            }
            throw new TokenException(TokenCode::INVALID_TOKEN);
        }
    }

    /**
     * 从请求头获取并解析 Token.
     */
    public static function getPayloadFromRequest(ServerRequestInterface $request, string $type = 'ORG'): array
    {
        try {
            self::init();

            $token_key = 'Org-Token';
            if ($type == 'SYS') {
                $token_key = 'System-Token';
            }

            $authHeader = $request->getHeaderLine($token_key);
            if (! $authHeader) {
                throw new TokenException(TokenCode::INVALID_TOKEN);
            }
            if (! str_starts_with($authHeader, 'Bearer ')) {
                $authHeader = 'Bearer ' . $authHeader;
            }

            $token = substr($authHeader, 7);

            return self::parseToken(token: $token);
        } catch (ExpiredException $e) {
            // 单独处理过期异常
            throw new TokenException(TokenCode::EXPIRED_TOKEN);
        } catch (Exception $e) {
            // 所有其他异常统一视为无效令牌
            throw new TokenException(TokenCode::INVALID_TOKEN);
        }
    }

    private static function getKey(string $type = 'ORG'): string
    {
        if ($type == 'SYS') {
            return self::$sys_key;
        }

        return self::$org_key;
    }
}

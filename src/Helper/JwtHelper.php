<?php

namespace TgkwAdc\Helper;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtHelper
{
    private static string $key;
    private static string $alg;

    public static function init(): void
    {
        self::$key = cfg('jwt.secret', 'default-secret');
        self::$alg = cfg('jwt.alg', 'default-secret');
    }

    /**
     * 生成 Token
     */
    public static function createToken(array $payload, int $ttl = 3600): string
    {
        $issuedAt = time();
        $expire = $issuedAt + $ttl;

        $tokenPayload = array_merge($payload, [
            'iat' => $issuedAt,
            'exp' => $expire,
        ]);

        return JWT::encode($tokenPayload, self::$key, self::$alg);
    }

    /**
     * 解析 Token
     */
    public static function parseToken(string $token): array
    {
        return (array) JWT::decode($token, new Key(self::$key, self::$alg));
    }

    /**
     * 从请求头获取并解析 Token
     */
    public static function getPayloadFromRequest(\Hyperf\HttpServer\Contract\RequestInterface $request): array
    {
        $authHeader = $request->header('Authorization', '');
        if (! $authHeader || ! str_starts_with($authHeader, 'Bearer ')) {
            throw new \Exception('Authorization header not found', 401);
        }

        $token = substr($authHeader, 7);

        return self::parseToken($token);
    }
}

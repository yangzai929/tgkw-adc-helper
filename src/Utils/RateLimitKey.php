<?php

namespace TgkwAdc\Utils;

use Hyperf\Context\ApplicationContext;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\HttpServer\Contract\RequestInterface;

class RateLimitKey
{
    public static function ip(ProceedingJoinPoint $proceedingJoinPoint): string
    {
        $request = ApplicationContext::getContainer()->get(RequestInterface::class);

        return self::ipFrom(
            forwardedFor: (string) $request->header('x-forwarded-for', ''),
            realIp: (string) $request->header('x-real-ip', ''),
            remoteAddr: (string) $request->server('remote_addr', ''),
        );
    }

    public static function ipWithPath(ProceedingJoinPoint $proceedingJoinPoint): string
    {
        $request = ApplicationContext::getContainer()->get(RequestInterface::class);

        return self::ipWithPathFrom(
            path: $request->getUri()->getPath(),
            forwardedFor: (string) $request->header('x-forwarded-for', ''),
            realIp: (string) $request->header('x-real-ip', ''),
            remoteAddr: (string) $request->server('remote_addr', ''),
        );
    }

    public static function ipFrom(string $forwardedFor, string $realIp, string $remoteAddr): string
    {
        return 'ip:' . self::clientIp($forwardedFor, $realIp, $remoteAddr);
    }

    public static function ipWithPathFrom(string $path, string $forwardedFor, string $realIp, string $remoteAddr): string
    {
        $path = '/' . ltrim($path, '/');

        return self::ipFrom($forwardedFor, $realIp, $remoteAddr) . ':' . $path;
    }

    private static function clientIp(string $forwardedFor, string $realIp, string $remoteAddr): string
    {
        $forwardedFor = trim($forwardedFor);
        if ($forwardedFor !== '') {
            $firstIp = trim(explode(',', $forwardedFor)[0]);
            if ($firstIp !== '') {
                return $firstIp;
            }
        }

        $realIp = trim($realIp);
        if ($realIp !== '') {
            return $realIp;
        }

        $remoteAddr = trim($remoteAddr);

        return $remoteAddr !== '' ? $remoteAddr : 'unknown';
    }
}
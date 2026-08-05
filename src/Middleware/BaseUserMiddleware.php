<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Middleware;

use Exception;
use Hyperf\Context\Context;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TgkwAdc\Constants\Code\AuthCode;
use TgkwAdc\Constants\GlobalConstants;
use TgkwAdc\Helper\ApiResponseHelper;
use TgkwAdc\Helper\JwtHelper;
use TgkwAdc\Helper\Log\LogHelper;

/*
 * 基础用户 token 认证中间件（不校验租户与权限）
 */
class BaseUserMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = JwtHelper::getTokenFromRequest($request, GlobalConstants::USER_TOKEN_TYPE);
        if (empty($token)) {
            return ApiResponseHelper::error(code: AuthCode::NEED_LOGIN, httpStatusCode: 401);
        }

        $isOfflineAuth = false;

        try {
            $payload = redis()->get(GlobalConstants::USER_TOKEN_REDIS_KEY_PREFIX . $token);
            if (! $payload) {
                return ApiResponseHelper::error(code: AuthCode::NEED_LOGIN, httpStatusCode: 401);
            }
            $user = json_decode($payload, true);
        } catch (Exception $e) {
            $jwtPayload = JwtHelper::getPayloadFromToken($token, GlobalConstants::USER_TOKEN_TYPE);
            if (empty($jwtPayload)) {
                return ApiResponseHelper::error(code: AuthCode::NEED_LOGIN, httpStatusCode: 401);
            }
            $isOfflineAuth = true;
            $user = $jwtPayload;
            LogHelper::warning('Redis 异常，临时离线认证', [
                'token' => $token,
                'payload' => $jwtPayload,
                'user' => $user,
                'error' => $e->getMessage(),
            ]);
        }

        if ($isOfflineAuth) {
            // 例如：禁止敏感操作，提示用户稍后重试 TODO
        }

        Context::set(GlobalConstants::BASE_USER_CONTEXT, $user);

        return $handler->handle($request);
    }
}

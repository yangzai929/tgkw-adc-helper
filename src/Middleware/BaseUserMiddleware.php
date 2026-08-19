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
use Throwable;

/*
 * 基础用户 token 认证中间件（不校验租户与权限）
 */
class BaseUserMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $tokenType = $request->getHeaderLine(GlobalConstants::ORG_TOKEN_KEY) !== ''
            ? GlobalConstants::ORG_TOKEN_TYPE
            : GlobalConstants::USER_TOKEN_TYPE;
        $tokenHeader = $tokenType === GlobalConstants::ORG_TOKEN_TYPE
            ? GlobalConstants::ORG_TOKEN_KEY
            : GlobalConstants::USER_TOKEN_KEY;

        try {
            $token = JwtHelper::getTokenFromRequest($request, $tokenType);
        } catch (Throwable $e) {
            $this->logAuth('warning', '基础用户认证失败：Token 提取异常', array_merge(
                $this->buildAuthLogContext($request, tokenHeader: $tokenHeader),
                [
                    'stage' => 'token_extraction',
                    'reason' => 'token_header_missing_or_invalid',
                    'exception_class' => $e::class,
                    'exception_code' => $e->getCode(),
                    'exception_message' => $e->getMessage(),
                ]
            ));
            throw $e;
        }

        if (empty($token)) {
            $this->logAuth('warning', '基础用户认证失败：Token 为空', array_merge(
                $this->buildAuthLogContext($request, $token, $tokenHeader),
                [
                    'stage' => 'token_extraction',
                    'reason' => 'empty_token',
                ]
            ));
            return ApiResponseHelper::error(code: AuthCode::NEED_LOGIN, httpStatusCode: 401);
        }

        $isOfflineAuth = false;
        $authSource = 'redis';

        try {
            $payload = redis()->get(GlobalConstants::ORG_TOKEN_REDIS_KEY_PREFIX . $token);
            if (! $payload) {
                $this->logAuth('warning', '基础用户认证失败：Redis 中未找到 Token', array_merge(
                    $this->buildAuthLogContext($request, $token, $tokenHeader),
                    [
                        'stage' => 'redis',
                        'reason' => 'token_cache_miss',
                        'redis_key_prefix' => GlobalConstants::ORG_TOKEN_REDIS_KEY_PREFIX,
                    ],
                    $this->buildJwtDiagnostic($token)
                ));
                return ApiResponseHelper::error(code: AuthCode::NEED_LOGIN, httpStatusCode: 401);
            }
            $user = json_decode($payload, true);

            if (! is_array($user)) {
                $this->logAuth('warning', '基础用户认证异常：Redis 用户数据不是有效 JSON 对象', array_merge(
                    $this->buildAuthLogContext($request, $token, $tokenHeader),
                    [
                        'stage' => 'redis_payload',
                        'reason' => 'invalid_cached_user_payload',
                        'json_error' => json_last_error_msg(),
                        'payload_type' => get_debug_type($payload),
                        'payload_length' => is_string($payload) ? strlen($payload) : null,
                    ]
                ));
            }
        } catch (Exception $e) {
            $this->logAuth('warning', '基础用户认证降级：Redis 访问异常', array_merge(
                $this->buildAuthLogContext($request, $token, $tokenHeader),
                [
                    'stage' => 'redis',
                    'reason' => 'redis_unavailable',
                    'exception_class' => $e::class,
                    'exception_code' => $e->getCode(),
                    'exception_message' => $e->getMessage(),
                ]
            ));

            try {
                $jwtPayload = JwtHelper::getPayloadFromToken($token, GlobalConstants::ORG_TOKEN_TYPE);
            } catch (Throwable $jwtException) {
                $this->logAuth('warning', '基础用户认证失败：离线 JWT 校验失败', array_merge(
                    $this->buildAuthLogContext($request, $token, $tokenHeader),
                    [
                        'stage' => 'jwt_fallback',
                        'reason' => 'jwt_validation_failed',
                        'redis_exception_class' => $e::class,
                        'jwt_exception_class' => $jwtException::class,
                        'jwt_exception_code' => $jwtException->getCode(),
                        'jwt_exception_message' => $jwtException->getMessage(),
                    ]
                ));
                throw $jwtException;
            }

            if (empty($jwtPayload)) {
                $this->logAuth('warning', '基础用户认证失败：离线 JWT Payload 为空', array_merge(
                    $this->buildAuthLogContext($request, $token, $tokenHeader),
                    [
                        'stage' => 'jwt_fallback',
                        'reason' => 'empty_jwt_payload',
                    ]
                ));
                return ApiResponseHelper::error(code: AuthCode::NEED_LOGIN, httpStatusCode: 401);
            }
            $isOfflineAuth = true;
            $authSource = 'jwt_fallback';
            $user = $jwtPayload;
            LogHelper::warning('Redis 异常，临时离线认证', [
                'token_fingerprint' => $this->tokenFingerprint($token),
                'user_id' => $this->resolveUserId($user),
                'error' => $e->getMessage(),
            ], filename: 'baseUserAuth');
        }

        if ($isOfflineAuth) {
            // 例如：禁止敏感操作，提示用户稍后重试 TODO
        }

        Context::set(GlobalConstants::BASE_USER_CONTEXT, $user);

        $this->logAuth('info', '基础用户认证成功', array_merge(
            $this->buildAuthLogContext($request, $token, $tokenHeader),
            [
                'stage' => 'completed',
                'reason' => 'authenticated',
                'auth_source' => $authSource,
                'offline_auth' => $isOfflineAuth,
                'user_id' => is_array($user) ? $this->resolveUserId($user) : null,
                'user_payload_type' => get_debug_type($user),
            ]
        ));

        return $handler->handle($request);
    }

    protected function logAuth(string $level, string $message, array $context): void
    {
        match ($level) {
            'info' => LogHelper::info($message, $context, filename: 'baseUserAuth'),
            'error' => LogHelper::error($message, $context, filename: 'baseUserAuth'),
            default => LogHelper::warning($message, $context, filename: 'baseUserAuth'),
        };
    }

    private function buildAuthLogContext(
        ServerRequestInterface $request,
        ?string $token = null,
        string $tokenHeader = GlobalConstants::USER_TOKEN_KEY
    ): array {
        return [
            'trace_id' => Context::get('trace_id'),
            'client_ip' => Context::get('client_ip')
                ?: $request->getHeaderLine('X-Forwarded-For')
                ?: $request->getHeaderLine('X-Real-IP'),
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath(),
            'user_agent' => Context::get('user_agent') ?: $request->getHeaderLine('User-Agent'),
            'token_header' => $tokenHeader,
            'token_header_present' => $request->getHeaderLine($tokenHeader) !== '',
            'token_length' => $token === null ? 0 : strlen($token),
            'token_fingerprint' => $this->tokenFingerprint($token),
        ];
    }

    private function tokenFingerprint(?string $token): ?string
    {
        return empty($token) ? null : substr(hash('sha256', $token), 0, 16);
    }

    private function buildJwtDiagnostic(string $token): array
    {
        $diagnostic = [
            'jwt_valid' => false,
            'jwt_user_id' => null,
            'jwt_session_id' => null,
            'jwt_idp_sub' => null,
            'jwt_token_type' => null,
            'jwt_iat' => null,
            'jwt_exp' => null,
            'jwt_exception_class' => null,
            'jwt_exception_code' => null,
            'jwt_exception_message' => null,
        ];

        try {
            $payload = JwtHelper::getPayloadFromToken($token, GlobalConstants::ORG_TOKEN_TYPE);

            return array_merge($diagnostic, [
                'jwt_valid' => true,
                'jwt_user_id' => $this->resolveUserId($payload),
                'jwt_session_id' => $payload['session_id'] ?? null,
                'jwt_idp_sub' => $payload['idp_sub'] ?? null,
                'jwt_token_type' => $payload['token_type'] ?? null,
                'jwt_iat' => $payload['iat'] ?? null,
                'jwt_exp' => $payload['exp'] ?? null,
            ]);
        } catch (Throwable $e) {
            return array_merge($diagnostic, [
                'jwt_exception_class' => $e::class,
                'jwt_exception_code' => $e->getCode(),
                'jwt_exception_message' => $e->getMessage(),
            ]);
        }
    }

    private function resolveUserId(array $user): int|string|null
    {
        foreach (['user_id', 'id', 'uid'] as $field) {
            if (isset($user[$field]) && (is_int($user[$field]) || is_string($user[$field]))) {
                return $user[$field];
            }
        }

        return null;
    }
}

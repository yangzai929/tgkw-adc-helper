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
use Hyperf\Context\ApplicationContext;
use Hyperf\Context\Context;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\HttpServer\Router\Dispatched;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TgkwAdc\Constants\Code\AuthCode;
use TgkwAdc\Constants\GlobalConstants;
use TgkwAdc\Helper\ApiResponseHelper;
use TgkwAdc\Helper\JwtHelper;
use TgkwAdc\Helper\Log\LogHelper;
use TgkwAdc\JsonRpc\Public\PublicServiceInterface;

/*
 * 系统后台 管理员token认证及权限校验中间件
 */
class SystemMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 1.获取token
        $token = JwtHelper::getTokenFromRequest($request, GlobalConstants::SYS_TOKEN_TYPE);
        if (empty($token)) {
            return ApiResponseHelper::error(code: AuthCode::NEED_LOGIN, httpStatusCode: 401);
        }

        $isOfflineAuth = false; // 标记是否走了离线认证

        try {
            $payload = redis()->get(GlobalConstants::SYS_TOKEN_REDIS_KEY_PREFIX . $token);
            if (! $payload) {
                return ApiResponseHelper::error(code: AuthCode::NEED_LOGIN, httpStatusCode: 401);
            }
            $user = json_decode($payload, true);
        } catch (Exception $e) {
            $jwtPayload = JwtHelper::getPayloadFromToken($token, GlobalConstants::SYS_TOKEN_TYPE);
            if (empty($jwtPayload)) {
                // 未登录：直接返回错误响应（避免抛出异常，统一响应格式）
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
        // 如果是离线认证考虑做降级处理
        if ($isOfflineAuth) {
            // 例如：禁止敏感操作，提示用户稍后重试 TODO
        }

        Context::set(GlobalConstants::SYS_ADMIN_CONTEXT, $user);

        // 2.权限校验
        // 获取当前请求的控制器名和方法名
        $controller = null;
        $action = null;
        $dispatched = $request->getAttribute(Dispatched::class);
        if ($dispatched && $dispatched->handler) {
            $callback = $dispatched->handler->callback;
            if (is_array($callback) && count($callback) >= 2) {
                $controller = is_object($callback[0]) ? get_class($callback[0]) : $callback[0];
                $action = $callback[1];
            } elseif (is_string($callback)) {
                if (strpos($callback, '@') !== false) {
                    [$controller, $action] = explode('@', $callback, 2);
                } else {
                    $controller = $callback;
                }
            }
        }

        // 获取当前请求的控制器方法菜单权限注解
        $annotations = AnnotationCollector::getClassMethodAnnotation($controller, $action);
        $annotationsArr = (array) $annotations;
        if (isset($annotationsArr['TgkwAdc\Annotation\SystemPermission'])) {  // 判断当前请求的菜单权限注解是否存在
            if ($controller && $action) {
                $action = $controller . '@' . $action;

                if ($user['is_root']) {
                    // 当前租户的超级管理员 默认具备所有权限直接放行
                    return $handler->handle($request);
                }

                $hasAccess = $this->hasAccess([$user['id'], $action, $action]);
                LogHelper::info('SystemMiddleware', ['controller' => $controller, 'action' => $action, 'res' => $hasAccess, $annotations]);
                if ($hasAccess) {
                    return $handler->handle($request);
                }

                return ApiResponseHelper::error(code: AuthCode::AUTH_ERROR, httpStatusCode: 403);
            }
            throw new Exception('权限中间件异常');
        }   // 菜单权限注解不存在 则不校验权限直接放行
        return $handler->handle($request);
    }

    private function hasAccess($params): bool
    {
        return true;
        if (env('APP_NAME') == 'public' && class_exists('\App\JsonRpc\Provider\SystemService')) {
            $sysAdminServiceRes = make('\App\JsonRpc\Provider\SystemService')->checkAccessPermission($params);
        } else {
            $sysAdminServiceRes = ApplicationContext::getContainer()->get(PublicServiceInterface::class)->checkAccessPermission($params);
        }

        if (isset($sysAdminServiceRes['data']['hasAccess'])) {
            return $sysAdminServiceRes['data']['hasAccess'];
        }
        return false;
    }
}

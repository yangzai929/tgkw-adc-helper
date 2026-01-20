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
use TgkwAdc\JsonRpc\User\UserServiceInterface;

/*
 * 租户（机构）用户token认证及权限校验中间件
 */
class OrgMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 1.获取token
        $token = JwtHelper::getTokenFromRequest($request, GlobalConstants::ORG_TOKEN_TYPE);
        if (empty($token)) {
            return ApiResponseHelper::error(code: AuthCode::NEED_LOGIN, httpStatusCode: 401);
        }

        $isOfflineAuth = false; // 标记是否走了离线认证

        try {
            $payload = redis()->get(GlobalConstants::ORG_TOKEN_REDIS_KEY_PREFIX . $token);
            if (! $payload) {
                return ApiResponseHelper::error(code: AuthCode::NEED_LOGIN, httpStatusCode: 401);
            }
            $user = json_decode($payload, true);
        } catch (Exception $e) {
            // 兜底
            $jwtPayload = JwtHelper::getPayloadFromToken($token, GlobalConstants::ORG_TOKEN_TYPE);
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
        // 如果是离线认证考虑做降级处理
        if ($isOfflineAuth) {
            // 例如：禁止敏感操作，提示用户稍后重试 TODO
        }

        Context::set(GlobalConstants::ORG_USER_CONTEXT, $user);

        // 2.权限校验
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

        // 判断action 是否在 无需校验租户及权限的列中
        $whiteList = [
            'App\Controller\V1\AuthController@logout',
            'App\Controller\V1\AuthController@refreshToken',
            'App\Controller\V1\AuthController@switchTenant',
            'App\Controller\V1\AuthController@getDevices',
            'App\Controller\V1\AuthController@kickoutDevice',
        ];
        if (in_array($controller . '@' . $action, $whiteList)) {
            return $handler->handle($request);
        }

        // 必须属于至少一个租户
        if (! $user['current_tenant_id'] && empty($user['tenants'])) {
            return ApiResponseHelper::error(code: AuthCode::NEED_JOIN_TENANT, httpStatusCode: 403);
        }

        if (! $user['current_tenant_id']) {
            return ApiResponseHelper::error(code: AuthCode::NEED_SELECT_TENANT, httpStatusCode: 403);
        }

        // 存储关键信息到协程上下文（供后续控制器/服务使用）
        Context::set(GlobalConstants::CURRENT_TENANT_ID, $user['current_tenant_id']);
        if ($user['is_current_tenant_main_admin']) {
            // 当前租户的超级管理员 默认具备所有权限直接放行
            Context::set(GlobalConstants::IS_CURRENT_TENANT_MAIN_ADMIN, true);
            return $handler->handle($request);
        }
        // 获取当前请求的控制器方法菜单权限注解
        $annotations = AnnotationCollector::getClassMethodAnnotation($controller, $action);
        $annotationsArr = (array) $annotations;
        if (isset($annotationsArr['TgkwAdc\Annotation\OrgPermission'])) {  // 判断当前请求的菜单权限注解是否存在
            if ($controller && $action) {
                $action = $controller . '@' . $action;
                // $hasAccess = Enforcer::enforce('user:1', 'tenant:1', 'App\Controller\V1\UserController@index');
                $hasAccess = $this->hasAccess(['user:' . $user['id'], 'tenant:' . $user['current_tenant_id'], $action]);
                LogHelper::info('OrgPermissionMiddleware', ['controller' => $controller, 'action' => $action, 'res' => $hasAccess, $annotations]);
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
        if (env('APP_NAME') == 'user' && class_exists('\App\JsonRpc\Provider\UserService')) {
            $userServiceRes = make('\App\JsonRpc\Provider\UserService')->checkAccessPermission($params);
        } else {
            $userServiceRes = ApplicationContext::getContainer()->get(UserServiceInterface::class)->checkAccessPermission($params);
        }

        if (isset($userServiceRes['data']['hasAccess'])) {
            return $userServiceRes['data']['hasAccess'];
        }
        return false;
    }
}

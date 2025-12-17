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
use TgkwAdc\Exception\BusinessException;
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
            return ApiResponseHelper::error(code: AuthCode::NEED_LOGIN->getCode());
        }

        $isOfflineAuth = false; // 标记是否走了离线认证

        try {
            $user = redis()->get($token);
            if (! $user) {
                return ApiResponseHelper::error(code: AuthCode::NEED_LOGIN->getCode());
            }
        } catch (Exception $e) {
            // 兜底
            $jwtPayload = JwtHelper::getPayloadFromToken($token, GlobalConstants::ORG_TOKEN_TYPE);
            if (empty($jwtPayload)) {
                return ApiResponseHelper::error(code: AuthCode::NEED_LOGIN->getCode());
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
            // 例如：禁止敏感操作，提示用户稍后重试
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

        // 获取当前请求的控制器方法菜单权限注解
        $annotations = AnnotationCollector::getClassMethodAnnotation($controller, $action);
        $annotationsArr = (array) $annotations;
        if (isset($annotationsArr['TgkwAdc\Annotation\OrgPermission'])) {  // 判断当前请求的菜单权限注解是否存在
            if ($controller && $action) {
                $action = $controller . '@' . $action;

                // 租户关联校验（确保用户有权访问当前租户）
                $userAuthorizedTenants = $user['tenantsArr'] ?? [];
                if (! empty($tenantId) && ! in_array($tenantId, $userAuthorizedTenants, true)) {
                    throw new BusinessException(AuthCode::ERROR_TENANT_ID);
                }

                // 存储关键信息到协程上下文（供后续控制器/服务使用）
                Context::set('tenant_id', $tenantId);
                foreach ($user['tenants'] as $tenant) {
                    if ($tenant['admin_uid'] == $user['id']) {
                        // 当前租户的超级管理员 默认具备所有权限直接放行
                        return $handler->handle($request);
                    }
                }

                // $hasAccess = Enforcer::enforce('user:1', 'tenant:1', 'App\Controller\V1\UserController@index');
                $hasAccess = $this->hasAccess(['user:' . $user['id'], 'tenant:' . $tenantId, $action]);
                LogHelper::info('OrgPermissionMiddleware', ['controller' => $controller, 'action' => $action, 'res' => $hasAccess, $annotations]);
                if ($hasAccess) {
                    return $handler->handle($request);
                }

                return ApiResponseHelper::error(AuthCode::AUTH_ERROR->getMsg(), code: AuthCode::AUTH_ERROR->getCode());
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

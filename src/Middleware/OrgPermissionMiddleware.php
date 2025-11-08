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
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\HttpServer\Router\Dispatched;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TgkwAdc\Constants\Code\CommonCode;
use TgkwAdc\Helper\ApiResponseHelper;
use TgkwAdc\Helper\Log\LogHelper;
use TgkwAdc\JsonRpc\User\UserServiceInterface;

class OrgPermissionMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
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
        if (isset($annotationsArr['TgkwAdc\Annotation\OrgPermission'])) {  // 判断当前请求的菜单权限注解是否存在
            if ($controller && $action) {
                $action = $controller . '@' . $action;
                $user = context_get('nowUser');
                $tenant_id = context_get('tenant_id');
                if (! $user) {
                    return ApiResponseHelper::error(CommonCode::NEED_LOGIN);
                }
                // $hasAccess = Enforcer::enforce('user:1', 'tenant:1', 'App\Controller\V1\UserController@index');
                $hasAccess = $this->hasAccess(['user:' . $user['id'], 'tenant:' . $tenant_id, $action]);
                LogHelper::info('OrgPermissionMiddleware', ['controller' => $controller, 'action' => $action, 'res' => $hasAccess, $annotations]);
                if ($hasAccess) {
                    return $handler->handle($request);
                }

                return ApiResponseHelper::error(CommonCode::AUTH_ERROR->getMsg(), code: CommonCode::AUTH_ERROR->getCode());
            }
            throw new Exception('权限中间件异常');
        }   // 菜单权限注解不存在 则不校验权限直接放行
        return $handler->handle($request);
    }

    private function hasAccess($params): bool
    {
        // TODO 此方法待完善 ，因在除用户服务外其他服务中所有请求的权限验证均为远程调用，高频调用情况下存在跨服务调用网络开销大的问题
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

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
use TgkwAdc\Constants\Code\AuthCode;
use TgkwAdc\Helper\ApiResponseHelper;
use TgkwAdc\Helper\Log\LogHelper;
use TgkwAdc\JsonRpc\Public\PublicServiceInterface;

class SystemPermissionMiddleware implements MiddlewareInterface
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
        if (isset($annotationsArr['TgkwAdc\Annotation\SystemPermission'])) {  // 判断当前请求的菜单权限注解是否存在
            if ($controller && $action) {
                $action = $controller . '@' . $action;
                $user = context_get('nowAdmin');
                if (! $user) {
                    return ApiResponseHelper::error(AuthCode::NEED_LOGIN);
                }
                $hasAccess = $this->hasAccess(['admin:' . $user['id'], $action]);
                LogHelper::info('SystemPermissionMiddleware', ['controller' => $controller, 'action' => $action, 'res' => $hasAccess, $annotations]);
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

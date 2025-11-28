<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TgkwAdc\Constants\Code\AuthCode;
use TgkwAdc\Exception\BusinessException;
use TgkwAdc\Helper\ApiResponseHelper;
use TgkwAdc\Helper\JwtHelper;

class AuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        // 白名单路由 再此列表中的不需要检查登录态 从配置文件读取白名单
        $a_whitelist = cfg('route_whitelist.auth_whitelist', []);
        if (! in_array($path, $a_whitelist, true)) {
            $t_whitelist = cfg('route_whitelist.tenant_whitelist', []);
            $tenantId = $request->getHeaderLine('Current-Tenant-Id');
            // 如果命中白名单，跳过租户 ID 检查
            if (! in_array($path, $t_whitelist, true) && empty($tenantId)) {
                throw new BusinessException(AuthCode::EMPTY_TENANT_ID);
            }

            // 获取JWT数据
            $jwtData = JwtHelper::getPayloadFromRequest($request, 'ORG');
            if (! $jwtData) { // 未登录
                return ApiResponseHelper::error(code: AuthCode::NEED_LOGIN->getCode());
            }

            // 检查租户id 是否正确
            if (! in_array($tenantId, $jwtData['tenantsArr'], true)) {
                throw new BusinessException(AuthCode::ERROR_TENANT_ID);
            }

            // 将租户id存储到协程上下文
            context_set('tenant_id', $tenantId);

            // 将登录信息存储到协程上下文
            context_set('nowUser', $jwtData);
        }

        return $handler->handle($request);
    }
}

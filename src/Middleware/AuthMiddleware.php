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
use TgkwAdc\Constants\Code\CommonCode;
use TgkwAdc\Exception\BusinessException;
use TgkwAdc\Helper\ApiResponseHelper;
use TgkwAdc\Helper\JwtHelper;

class AuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        // 白名单路由 再此列表中的不需要检查登录态 从配置文件读取白名单
        $a_whitelist = cfg('auth_whitelist.auth_whitelist', []);
        if (! in_array($path, $a_whitelist, true)) {
            $t_whitelist = cfg('auth_whitelist.tenant_whitelist', []);
            $tenantId = $request->getHeaderLine('Current-Tenant-Id');
            // 如果命中白名单，跳过租户 ID 检查
            if (! in_array($path, $t_whitelist, true) && empty($tenantId)) {
                throw new BusinessException(CommonCode::EMPTY_TENANT_ID);
            }
            // 将租户id存储到上下文中，供其他地方使用
            context_set('tenant_id', $tenantId);

            // 获取JWT数据
            $jwtData = JwtHelper::getPayloadFromRequest($request, 'ORG');
            //        if (! $jwtData || time() - $jwtData->iat > 86400 * 14) { // 未登录，或登录状态超过14天
            if (! $jwtData) { // 未登录，或登录状态超过14天
                return ApiResponseHelper::error(CommonCode::NEED_LOGIN->getI18nMsg(), code: CommonCode::NEED_LOGIN->getCode());
            }

            context_set('nowUser', $jwtData); // 将登录信息存储到协程上下文
        }

        return $handler->handle($request);
    }
}

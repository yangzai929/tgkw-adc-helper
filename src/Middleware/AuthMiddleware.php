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
use Hyperf\Context\Context; // 明确引入协程上下文类（原代码可能依赖全局函数）

class AuthMiddleware implements MiddlewareInterface
{
    /**
     * 处理请求：执行认证逻辑，通过后传递给下一个处理器
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 1. 获取当前请求路径（不含查询参数）
        $currentPath = $request->getUri()->getPath();

        // 2. 读取配置中的白名单（无需登录的路由）
        $authWhitelist = $this->getConfigWhitelist('auth_whitelist');
        $tenantWhitelist = $this->getConfigWhitelist('tenant_whitelist');

        // 3. 白名单路由直接放行（无需后续认证）
        if (in_array($currentPath, $authWhitelist, true)) {
            return $handler->handle($request);
        }

        // 4. 租户ID校验（非租户白名单路由必须携带）
        $tenantId = trim($request->getHeaderLine('Current-Tenant-Id'));
        if (!in_array($currentPath, $tenantWhitelist, true) && empty($tenantId)) {
            throw new BusinessException(AuthCode::EMPTY_TENANT_ID);
        }

        // 5. JWT登录态校验（获取ORG类型的令牌 payload）
        $jwtPayload = JwtHelper::getPayloadFromRequest($request, 'ORG');
        if (empty($jwtPayload)) {
            // 未登录：直接返回错误响应（避免抛出异常，统一响应格式）
            return ApiResponseHelper::error(code: AuthCode::NEED_LOGIN->getCode());
        }

        // 6. 租户权限校验（确保用户有权访问当前租户）
        $userAuthorizedTenants = $jwtPayload['tenantsArr'] ?? [];
        if (!empty($tenantId) && !in_array($tenantId, $userAuthorizedTenants, true)) {
            throw new BusinessException(AuthCode::ERROR_TENANT_ID);
        }

        // 7. 存储关键信息到协程上下文（供后续控制器/服务使用）
        $this->setContextData([
            'tenant_id' => $tenantId,
            'nowUser' => $jwtPayload,
        ]);

        // 8. 认证通过，传递请求给下一个处理器
        return $handler->handle($request);
    }

    /**
     * 读取配置中的路由白名单
     * @param string $key 白名单配置键名（auth_whitelist/tenant_whitelist）
     * @return array 白名单路由数组（默认空数组）
     */
    private function getConfigWhitelist(string $key): array
    {
        $whitelist = cfg("route_whitelist.{$key}", []);
        // 确保返回数组类型，避免配置错误导致的类型问题
        return is_array($whitelist) ? $whitelist : [];
    }

    /**
     * 批量存储数据到协程上下文
     * @param array $data 键值对数据
     */
    private function setContextData(array $data): void
    {
        foreach ($data as $key => $value) {
            Context::set($key, $value);
        }
    }
}
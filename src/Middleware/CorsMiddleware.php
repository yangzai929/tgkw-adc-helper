<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Middleware;

use Hyperf\Context\Context;
use Hyperf\HttpMessage\Exception\BadRequestHttpException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Swoole\Http\Response;

class CorsMiddleware implements MiddlewareInterface
{
    /**
     * 允许的跨域域名
     * @var array
     */
    protected $allowOrigins = [
        "*"
    ];

    /**
     * 允许的 HTTP 方法
     * @var string
     */
    protected $allowMethods = 'GET,POST,PUT,DELETE,OPTIONS,PATCH';

    /**
     * 允许的请求头
     * @var string
     */
    protected $allowHeaders = 'DNT,Keep-Alive,User-Agent,Cache-Control,Content-Type,Authorization,Language,Org-Token,Current-Tenant-Id,System-token';

    /**
     * 预检请求的缓存时间（秒）
     * @var int
     */
    protected $maxAge = 86400;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 1. 获取或创建 Response 实例（避免 Context 中无实例导致的 null 错误）
        $response = Context::get(ResponseInterface::class) ?? new Response();

        // 2. 获取请求的 Origin 头
        $origin = $request->getHeaderLine('Origin');

        // 3. 处理跨域头（优先匹配允许的 Origin，支持通配符如 *.tgkw.com）
        $allowOrigin = $this->getAllowOrigin($origin);
        if ($allowOrigin) {
            $response = $response->withHeader('Access-Control-Allow-Origin', $allowOrigin)
                ->withHeader('Access-Control-Allow-Credentials', 'true')
                ->withHeader('Access-Control-Allow-Methods', $this->allowMethods)
                ->withHeader('Access-Control-Allow-Headers', $this->allowHeaders)
                ->withHeader('Access-Control-Max-Age', (string)$this->maxAge);
        }

        // 4. 将处理后的 Response 放回 Context
        Context::set(ResponseInterface::class, $response);

        // 5. 处理 OPTIONS 预检请求（直接返回响应，不执行后续中间件）
        if ($request->getMethod() === 'OPTIONS') {
            return $response->withStatus(204); // 204 表示无内容，符合 OPTIONS 请求规范
        }

        // 6. 执行后续中间件并返回响应
        return $handler->handle($request);
    }

    /**
     * 匹配允许的 Origin
     * @param string $origin
     * @return string|null
     */
    protected function getAllowOrigin(string $origin): ?string
    {
        // 空 Origin（如本地文件请求）直接返回 null
        if (empty($origin)) {
            return null;
        }

        // 允许所有域名（仅开发环境使用，生产环境禁止）
        if (in_array('*', $this->allowOrigins)) {
            return '*';
        }

        // 精确匹配
        if (in_array($origin, $this->allowOrigins)) {
            return $origin;
        }

        // 通配符匹配（如 *.tgkw.com）
        foreach ($this->allowOrigins as $allowOrigin) {
            if (strpos($allowOrigin, '*') !== false) {
                $domain = str_replace('*.', '', $allowOrigin);
                if (substr($origin, -strlen($domain)) === $domain) {
                    return $origin;
                }
            }
        }

        return null;
    }
}
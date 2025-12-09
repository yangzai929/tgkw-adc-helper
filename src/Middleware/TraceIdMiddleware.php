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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ramsey\Uuid\Uuid;

class TraceIdMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 1. 从请求头获取 X-Trace-Id，若不存在则生成
        $traceId = $request->getHeaderLine('X-Trace-Id');
        if (empty($traceId)) {
            // 生成 UUID 作为 Trace-Id（需先安装：composer require ramsey/uuid）
            $traceId = str_replace('-', '', Uuid::uuid4()->toString());
        }

        // 2. 将 Trace-Id 存入上下文（方便全局获取）
        Context::set('trace_id', $traceId);

        // 3. 继续处理请求
        $response = $handler->handle($request);

        // 4. 在响应头中回写 X-Trace-Id
        return $response->withHeader('X-Trace-Id', $traceId);
    }
}

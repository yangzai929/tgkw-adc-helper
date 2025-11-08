<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Exception\Handler;

use Hyperf\Context\Context;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Psr\Http\Message\ResponseInterface;

abstract class BaseExceptionHandler extends ExceptionHandler
{
    /**
     * 为响应统一加 trace-id.
     */
    protected function withTraceId(ResponseInterface $response): ResponseInterface
    {
        $traceId = Context::get('trace_id');
        if ($traceId) {
            return $response->withHeader('trace-id', $traceId);
        }

        return $response;
    }
}

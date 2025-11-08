<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Exception\Handler;

use Psr\Http\Message\ResponseInterface;
use TgkwAdc\Exception\BusinessException;
use TgkwAdc\Helper\ApiResponseHelper;
use TgkwAdc\Helper\Log\LogHelper;
use Throwable;

class BusinessExceptionHandler extends BaseExceptionHandler
{
    public function handle(Throwable $throwable, ResponseInterface $response)
    {
        if ($throwable instanceof BusinessException) {
            $this->stopPropagation(); // 阻止继续向下传播异常
            LogHelper::business(message: $throwable->getMessage(), context: [$throwable]);
            $resp = ApiResponseHelper::error($throwable->getMessage(), code: $throwable->getCode());
            return $this->withTraceId($resp);
        }

        return $response;
    }

    public function isValid(Throwable $throwable): bool
    {
        return $throwable instanceof BusinessException;
    }
}

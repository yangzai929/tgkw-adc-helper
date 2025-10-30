<?php

namespace TgkwAdc\Exception\Handler;

use Hyperf\ExceptionHandler\ExceptionHandler;
use Psr\Http\Message\ResponseInterface;
use TgkwAdc\Exception\BusinessException;
use TgkwAdc\Helper\ApiResponseHelper;
use TgkwAdc\Helper\Log\LogHelper;
use Throwable;

class BusinessExceptionHandler extends ExceptionHandler
{
    public function handle(Throwable $throwable, ResponseInterface $response)
    {
        if ($throwable instanceof BusinessException) {
            $this->stopPropagation(); // 阻止继续向下传播异常
            LogHelper::business(message: $throwable->getMessage(), context: [$throwable]);
            return ApiResponseHelper::error($throwable->getMessage(), code: 401);
        }

        return $response;
    }

    public function isValid(Throwable $throwable): bool
    {
        return $throwable instanceof BusinessException;
    }
}
<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Exception\Handler;

use Hyperf\Contract\StdoutLoggerInterface;
use Psr\Http\Message\ResponseInterface;
use TgkwAdc\Exception\TokenException;
use TgkwAdc\Helper\ApiResponseHelper;
use TgkwAdc\Helper\Log\LogHelper;
use Throwable;

class TokenExceptionHandler extends BaseExceptionHandler
{
    public function __construct(protected StdoutLoggerInterface $logger)
    {
    }

    public function handle(Throwable $throwable, ResponseInterface $response)
    {
        if ($throwable instanceof TokenException) {
            $this->stopPropagation(); // 阻止继续向下传播异常
            LogHelper::error(message: $throwable->getMessage(), context: [$throwable], filename: 'invalidToken');

            return ApiResponseHelper::error($throwable->getMessage(), code: 401);
        }

        // 交给下一个异常处理器
        return $response;
    }

    public function isValid(Throwable $throwable): bool
    {
        return $throwable instanceof TokenException;
    }
}

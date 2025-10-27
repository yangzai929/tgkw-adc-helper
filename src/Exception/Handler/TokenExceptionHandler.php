<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Exception\Handler;

use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\ExceptionHandler\ExceptionHandler;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use TgkwAdc\Helper\ApiResponseHelper;
use TgkwAdc\Helper\Log\LogHelper;
use Throwable;
use UnexpectedValueException;

class TokenExceptionHandler extends ExceptionHandler
{
    public function __construct(protected StdoutLoggerInterface $logger)
    {
    }

    public function handle(Throwable $throwable, ResponseInterface $response)
    {
        if ($throwable instanceof InvalidArgumentException
            || $throwable instanceof UnexpectedValueException
            || $throwable instanceof SignatureInvalidException
            || $throwable instanceof BeforeValidException
            || $throwable instanceof ExpiredException
        ) {
            $this->stopPropagation(); // 阻止继续向下传播异常
            LogHelper::error(message: $throwable->getMessage(), context: [$throwable], filename: 'invalidToken');
            return ApiResponseHelper::error($throwable->getMessage(), code: 401);
        }

        // 交给下一个异常处理器
        return $response;
    }

    public function isValid(Throwable $throwable): bool
    {
        return true;
    }
}

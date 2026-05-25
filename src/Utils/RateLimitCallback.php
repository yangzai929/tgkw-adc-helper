<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Utils;

use Hyperf\Di\Aop\ProceedingJoinPoint;
use Psr\Http\Message\ResponseInterface;
use TgkwAdc\Helper\ApiResponseHelper;

class RateLimitCallback
{
    public static function tooManyRequests(float $seconds, ProceedingJoinPoint $proceedingJoinPoint): ResponseInterface
    {
        return ApiResponseHelper::error(
            message: 'too many requests ,try again after ' . $seconds . ' seconds',
            code: 429,
            httpStatusCode: 429,
        );
    }
}

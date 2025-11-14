<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Aspect;

use Exception;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use TgkwAdc\Helper\ApiResponseHelper;
use TgkwAdc\Helper\Log\LogHelper;

class RpcServiceAspect extends AbstractAspect
{
    /**
     * 要拦截的类/方法.
     *
     * 可以拦截所有实现了 RPC 接口的类，也可以用通配符
     */
    public array $classes = [
        'App\JsonRpc\Provider\*::*', // 拦截所有 Provider 目录下的类方法
        'TgkwAdc\JsonRpc\Approval\*::*',
        'TgkwAdc\JsonRpc\Hr\*::*',
        'TgkwAdc\JsonRpc\Public\*::*',
        'TgkwAdc\JsonRpc\User\*::*',
        // add more
    ];

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        try {
            // 执行原方法
            return $proceedingJoinPoint->process();
        } catch (Exception $e) {
            // 统一记录日志
            LogHelper::error('RPC ERROR', [$e->getFile(), $e->getLine(), $e->getMessage()]);
            // 统一返回错误响应
            return ApiResponseHelper::genServiceError($e);
        }
    }
}

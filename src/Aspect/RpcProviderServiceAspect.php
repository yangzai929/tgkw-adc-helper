<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Aspect;

use Hyperf\Context\Context;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use TgkwAdc\Helper\Log\LogHelper;
use Throwable;

class RpcProviderServiceAspect extends AbstractAspect
{
    /**
     * 要拦截的类/方法.
     *
     * 用通配符拦截所有的远程服务提供者
     */
    public array $classes = [
        'App\JsonRpc\Provider\*::*',
        'TgkwAdc\JsonRpc\Provider\*::*',
    ];

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        try {
            // 方式1: 直接访问 arguments['keys']（推荐）
            $params = null;
            if (isset($proceedingJoinPoint->arguments['keys'][0]) && is_array($proceedingJoinPoint->arguments['keys'][0])) {
                $params = $proceedingJoinPoint->arguments['keys'][0];
            }

            // 方式2: 使用 getArguments()（如果 order 存在）
            $arguments = $proceedingJoinPoint->getArguments();
            if (! empty($arguments) && is_array($arguments[0])) {
                $params = $arguments[0];
            }

            // 方式3: 使用反射获取参数名
            $reflectionMethod = $proceedingJoinPoint->getReflectMethod();
            $parameters = $reflectionMethod->getParameters();
            if (! empty($parameters) && isset($proceedingJoinPoint->arguments['keys'][0])) {
                $params = $proceedingJoinPoint->arguments['keys'][0];
            }

            // 获取语言变量
            $lang = $params['_lang'] ?? $params['X-RPC-LANG'] ?? 'zh-CN';

            Context::set('locale', $lang);

            LogHelper::info('RPC PROVIDER PROCESS INFO', ['class' => $proceedingJoinPoint->className, 'method' => $proceedingJoinPoint->methodName, 'params' => $params]);
            // 执行原方法
            return $proceedingJoinPoint->process();
        } catch (Throwable $e) {
            //            // 统一记录日志
            LogHelper::error('RPC PROVIDER PROCESS ERROR', ['file' => $e->getFile(), 'line' => $e->getLine(), 'error_msg' => $e->getMessage()]);
            throw $e; //继续抛出
        }
    }
}

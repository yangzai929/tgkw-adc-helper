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
use TgkwAdc\Exception\BusinessException;
use TgkwAdc\Helper\ApiResponseHelper;
use TgkwAdc\Helper\Log\LogHelper;
use Throwable;

class RpcConsumerServiceAspect extends AbstractAspect
{
    /**
     * 要拦截的类/方法.
     *
     * 可以拦截所有实现了 RPC 接口的类，也可以用通配符
     */
    public array $classes = [
        'App\JsonRpc\Consumer\*::*',
        'App\JsonRpc\Consumer\*\*::*',
        'App\JsonRpc\Consumer\*\*\*::*',
        'App\JsonRpc\Consumer\*\*\*\*::*',
        'App\JsonRpc\Consumer\*\*\*\*\*::*',

        'TgkwAdc\JsonRpc\Consumer\*::*',
        'TgkwAdc\JsonRpc\Consumer\*\*::*',
        'TgkwAdc\JsonRpc\Consumer\*\*\*::*',
        'TgkwAdc\JsonRpc\Consumer\*\*\*\*::*',
        'TgkwAdc\JsonRpc\Consumer\*\*\*\*\*::*',
    ];

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        try {
            // 1. 从上下文获取语言变量
            $lang = Context::get('locale', 'zh_CN'); // 兜底默认中文
            // 2. 将语言变量添加到 RPC 请求参数中
            if (isset($proceedingJoinPoint->arguments['keys'])) {
                foreach ($proceedingJoinPoint->arguments['keys'] as $key => $value) {
                    if (is_array($value)) {
                        $proceedingJoinPoint->arguments['keys'][$key]['_lang'] = $lang;
                        $proceedingJoinPoint->arguments['keys'][$key]['X-RPC-LANG'] = $lang;
                    }
                }
            }

            $response =  $proceedingJoinPoint->process();

            // 统一异常处理（逻辑同通用方法）
            if (isset($response['code']) && isset($response['data']['class'])) {
                $exceptionClass = $response['data']['class'];
                if ($exceptionClass === BusinessException::class) {
                    return [
                        'is_exception' => true,
                        'type' => 'business',
                        'message' => $response['message'],
                        'code' => $response['data']['code'],
                    ];
                }
                return [
                    'is_exception' => true,
                    'type' => 'system',
                    'message' => 'service error',
                    'code' => $response['code'],
                    'error' => $response['message'],
                ];
            }
            return $response;
        } catch (Throwable $e) {
            // 统一记录日志
            LogHelper::error('RPC ERROR', ['file' => $e->getFile(), 'line' => $e->getLine(), 'error_msg' => $e->getMessage()]);
            // 统一返回错误响应
            $data = ['error' => ['file' => $e->getFile(), 'line' => $e->getLine(), 'error_msg' => $e->getMessage()]];
            return ApiResponseHelper::genServiceError($e, messges: 'RPC ERROR', data: $data);
        }
    }
}

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
use Hyperf\Coroutine\Coroutine;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Ramsey\Uuid\Uuid;
use TgkwAdc\Exception\BusinessException;
use TgkwAdc\Helper\ApiResponseHelper;
use TgkwAdc\Helper\Log\LogHelper;
use Throwable;

class RpcConsumerServiceAspect extends AbstractAspect
{
    /**
     * 要拦截的类/方法.
     * 单个 * 匹配所有子目录
     * 可以拦截所有实现了 RPC 接口的类，也可以用通配符
     */
    public array $classes = [
        'App\JsonRpc\Consumer\*::*',
        'TgkwAdc\JsonRpc\Consumer\*::*',
    ];

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        try {
            $traceId = Context::get('trace_id', str_replace('-', '', Uuid::uuid4()->toString()));
            $serviceName = $proceedingJoinPoint->className;
            $methodName = $proceedingJoinPoint->methodName;
            $params = $proceedingJoinPoint->arguments['keys'] ?? [];

            // 1. 从上下文获取语言变量
            $lang = Context::get('locale', 'zh_CN'); // 兜底默认中文

            // 2. 构建日志基础上下文
            $logContext = [
                'trace_id' => $traceId,
                'service' => $serviceName,
                'method' => $methodName,
                'params' => $params,
                'lang' => $lang,
                'coroutine_id' => Coroutine::id(),
            ];

            $this->injectLangParams($proceedingJoinPoint, $lang);

            $response =  $proceedingJoinPoint->process();
            LogHelper::error('RPC CONSUMER SERVICE INFO call', $logContext);
            LogHelper::error('RPC CONSUMER SERVICE INFO response', $response);
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
            $logContext['error'] = [
                'msg' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'exception_class' => get_class($e),
            ];
            LogHelper::error('RPC ERROR', $logContext);
            // 统一返回错误响应
            $data = [
                'error' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'error_msg' => $e->getMessage()
                ]
            ];
            return ApiResponseHelper::genServiceError($e, messges: 'RPC ERROR', data: $data);
        }
    }


    /**
     * 注入语言参数（兼容非数组参数）
     */
    private function injectLangParams(ProceedingJoinPoint $pjp, string $lang): void
    {
        if (empty($pjp->arguments['keys'])) {
            $pjp->arguments['keys'] = ['_lang' => $lang, 'X-RPC-LANG' => $lang];
            return;
        }

        foreach ($pjp->arguments['keys'] as &$value) {
            if (is_array($value)) {
                $value['_lang'] = $lang;
                $value['X-RPC-LANG'] = $lang;
            } elseif (is_object($value) && method_exists($value, 'setAttribute')) {
                // 兼容对象参数（比如DTO）
                $value->setAttribute('_lang', $lang);
                $value->setAttribute('X-RPC-LANG', $lang);
            }
        }
    }
}

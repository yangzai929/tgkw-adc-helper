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
use Hyperf\JsonRpc\ResponseBuilder;
use Ramsey\Uuid\Uuid;
use TgkwAdc\Helper\Log\LogHelper;
use Throwable;

class RpcConsumerServiceAspect extends AbstractAspect
{
    /**
     * 要拦截的类/方法.
     * 用通配符拦截所有的远程服务调用者.
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

            $response = $proceedingJoinPoint->process();

            if (isset($response['code']) && $response['code'] < 0) {
                LogHelper::error('RPC CONSUMER SERVICE call', $logContext);
                LogHelper::error('RPC CONSUMER SERVICE response with error', $response);
            } else {
                LogHelper::info('RPC CONSUMER SERVICE call', $logContext);
                LogHelper::info('RPC CONSUMER SERVICE response', $response);
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
            LogHelper::error('RPC CONSUMER PROCESS ERROR', $logContext);
            // 统一返回错误响应
            $data = [
                'error' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'error_msg' => $e->getMessage(),
                ],
            ];
            return [
                'code' => ResponseBuilder::INVALID_REQUEST,
                'message' => $e->getMessage(),
                'data' => $data,
            ];
        }
    }

    /**
     * 注入语言参数（兼容非数组参数）.
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

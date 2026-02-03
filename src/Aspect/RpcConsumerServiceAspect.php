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

            $this->injectParams($proceedingJoinPoint, ['_lang' => $lang, 'X-RPC-LANG' => $lang, 'trace_id' => $traceId]);

            $response = $proceedingJoinPoint->process();

            if (isset($response['code']) && $response['code'] < 0) {
                LogHelper::error('RPC CONSUMER SERVICE call', $logContext);
                LogHelper::error('RPC CONSUMER SERVICE response with error', $response);
            } else {
                LogHelper::info('RPC CONSUMER SERVICE call', $logContext);
                LogHelper::info('RPC CONSUMER SERVICE response', [$response]);
            }
            return $response;
        } catch (Throwable $e) {
            // 统一记录日志
            $logContext['error'] = [
                'msg' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTrace(), // 详细堆栈（数组）
                'trace_string' => $e->getTraceAsString(), // 字符串格式的堆栈（可选）
                'exception_class' => get_class($e),
            ];
            LogHelper::error('RPC CONSUMER PROCESS ERROR', $logContext);
            // 统一返回错误响应
            $data = [
                'error' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTrace(), // 详细堆栈（数组）
                    'trace_string' => $e->getTraceAsString(), // 字符串格式的堆栈（可选）
                    'error_msg' => $e->getMessage(),
                ],
            ];
            return [
                'code' => ResponseBuilder::INVALID_REQUEST,
                'message' => 'Server Error',
                'data' => $data,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 通用参数注入方法.
     * @param ProceedingJoinPoint $pjp 连接点对象
     * @param array $params 要注入的参数键值对（如 ['_lang' => $lang, 'X-RPC-LANG' => $lang] 或 ['trace_id' => $traceId]）
     */
    private function injectParams(ProceedingJoinPoint $pjp, array $params): void
    {
        // 如果参数列表为空，直接初始化并赋值
        if (empty($pjp->arguments['keys'])) {
            $pjp->arguments['keys'] = $params;
            return;
        }

        // 遍历参数列表，注入指定参数
        foreach ($pjp->arguments['keys'] as &$value) {
            if (is_array($value)) {
                // 数组类型：批量赋值
                $value = array_merge($value, $params);
            } elseif (is_object($value) && method_exists($value, 'setAttribute')) {
                // 对象类型（如DTO）：逐个调用 setAttribute
                foreach ($params as $key => $val) {
                    $value->setAttribute($key, $val);
                }
            }
        }
    }
}

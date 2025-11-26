<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Aspect;

use Hyperf\Database\ConnectionResolverInterface;
use Hyperf\Di\Annotation\Aspect;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * 控制器：识别手动事务 + 遗漏提交强制回滚 + 统一异常处理.
 * @Aspect
 */
class ControllerTransactionAspect extends AbstractAspect
{
    /**
     * 拦截所有控制器方法.
     */
    public array $classes = [
        'App\Controller\*::*',
    ];

    public function __construct(
        private ResponseInterface $response,
        private ConnectionResolverInterface $connectionResolver,
        private LoggerInterface $logger
    ) {
    }

    public function process(ProceedingJoinPoint $joinPoint)
    {
        $connection = $this->connectionResolver->connection(); // 默认数据库连接（支持多连接扩展）
        $controller = $joinPoint->className;
        $method = $joinPoint->methodName;

        // 1. 记录初始事务层级（执行控制器方法前）
        $initialTransactionLevel = $connection->transactionLevel();
        $hasManualTransaction = false; // 是否手动开启了事务

        try {
            // 执行控制器方法（触发手动事务操作）
            $result = $joinPoint->process();

            // 2. 执行后检查：是否存在未关闭的手动事务
            $finalTransactionLevel = $connection->transactionLevel();
            $hasManualTransaction = $finalTransactionLevel > $initialTransactionLevel;

            if ($hasManualTransaction) {
                // 若事务仍未提交（层级>0），强制回滚
                if ($finalTransactionLevel > 0) {
                    $connection->rollBack(); // 核心：强制回滚遗漏提交的事务
                    $this->logger->warning(sprintf(
                        '⚠️  控制器[%s->%s]手动开启事务但未提交，切面已强制回滚',
                        $controller,
                        $method
                    ), [
                        '初始层级' => $initialTransactionLevel,
                        '未提交层级' => $finalTransactionLevel,
                        '参数' => $joinPoint->arguments,
                    ]);

                    // 可选：返回友好提示（告知开发者事务已回滚）
                    return $this->response->json([
                        'code' => 400,
                        'message' => '事务未明确提交，已自动回滚（请检查代码中的事务提交逻辑）',
                        'data' => null,
                    ])->withStatus(400);
                }

                // 事务已正常提交（层级回到初始值）
                $this->logger->info(sprintf(
                    '✅  控制器[%s->%s]手动事务已正常提交',
                    $controller,
                    $method
                ));
            }

            return $result;
        } catch (Throwable $e) {
            // 3. 异常场景：检查是否存在未回滚的手动事务
            $finalTransactionLevel = $connection->transactionLevel();
            $hasManualTransaction = $finalTransactionLevel > $initialTransactionLevel;

            if ($hasManualTransaction && $finalTransactionLevel > 0) {
                $connection->rollBack(); // 强制回滚未处理的事务
                $this->logger->warning(sprintf(
                    '⚠️  控制器[%s->%s]手动开启事务，异常后未回滚，切面已强制回滚',
                    $controller,
                    $method
                ), [
                    '初始层级' => $initialTransactionLevel,
                    '未回滚层级' => $finalTransactionLevel,
                    '异常信息' => $e->getMessage(),
                ]);
            }

            // 记录异常日志
            $this->logger->error(sprintf(
                '❌  控制器[%s->%s]执行失败（手动事务：%s），错误：%s',
                $controller,
                $method,
                $hasManualTransaction ? '是' : '否',
                $e->getMessage()
            ), [
                '追踪信息' => $e->getTraceAsString(),
            ]);

            // 统一异常响应
            return $this->response->json([
                'code' => $e->getCode() ?: 500,
                'message' => env('APP_ENV') === 'dev' ? $e->getMessage() : '服务器内部错误',
                'data' => null,
            ])->withStatus(500);
        }
    }
}

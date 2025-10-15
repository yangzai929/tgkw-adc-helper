<?php

namespace TgkwAdc\Helper;

use Hyperf\Di\Annotation\AnnotationCollector;
use TgkwAdc\Helper\Log\LogHelper;


use TgkwAdc\Annotation\XxlJobTask as VendorXxlJobTask;

class XxlJobTaskHelper {

    /**
     * 构建并（可选）注册基于注解的 xxl-job 任务。
     */
    public function build(bool $register = false): void
    {
        $jobs = [];

        // 收集来自注解来源的任务定义（方法级注解）
        $methodsVendor = \class_exists(VendorXxlJobTask::class) ? AnnotationCollector::getMethodsByAnnotation(VendorXxlJobTask::class) : [];

        foreach ($methodsVendor as $method) {
            $annotation = $method['annotation'];
            $className = $method['class'];
            $methodName = $method['method'];

            $job = [
                'class' => $className,
                'method' => $methodName,
                'xxlVersion' => $annotation->xxlVersion ?? '3.2.0',
                'author' => $annotation->author ?? '机器注册(adc)',
                'jobDesc' => $annotation->jobDesc ?? '',
                'scheduleType' => $annotation->scheduleType ?? 'CRON',
                'cron' => $annotation->cron ?? '',
                'jobHandler' => $annotation->jobHandler ?: $methodName,
                'jobParam' => $annotation->jobParam ?? '',
                'routeStrategy' => $annotation->routeStrategy ?? '',
                'jobTimeout' => (int) ($annotation->jobTimeout ?? 0),
                'jobRetry' => (int) ($annotation->jobRetry ?? 0),
            ];

            $jobs[] = $job;
        }

        // 输出日志，便于确认收集到的任务与其关键信息
        LogHelper::info('xxl-job collected jobs', ['count' => count($jobs)]);
        foreach ($jobs as $job) {
            LogHelper::info('xxl-job job found', [
                'handler' => $job['jobHandler'],
                'cron' => $job['cron'],
                'desc' => $job['jobDesc'],
                'class' => $job['class'],
                'method' => $job['method'],
            ]);
        }

        // 预留注册逻辑：如需自动向 xxl-job-admin 注册，可在此实现 HTTP 调用。
        if ($register) {
            LogHelper::info('xxl-job register skipped (stub). Implement admin registration if needed.');
        }
    }
}
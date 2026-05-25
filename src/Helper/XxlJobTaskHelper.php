<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Helper;

use Hyperf\Di\Annotation\AnnotationCollector;
use TgkwAdc\Annotation\XxlJobTask as VendorXxlJobTask;
use TgkwAdc\Helper\Log\LogHelper;
use Throwable;

use function class_exists;

class XxlJobTaskHelper
{
    /**
     * 构建并（可选）注册基于注解的 xxl-job 任务。
     */
    public function build(bool $register = false): void
    {
        $jobs = [];

        // 收集来自注解来源的任务定义（方法级注解）
        $methodsVendor = class_exists(VendorXxlJobTask::class) ? AnnotationCollector::getMethodsByAnnotation(VendorXxlJobTask::class) : [];

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
                'blockStrategy' => $annotation->blockStrategy ?? '',
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

        if ($register) {
            $this->registerJobs($jobs);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $jobs
     */
    private function registerJobs(array $jobs): void
    {
        if ($jobs === []) {
            LogHelper::info('xxl-job register skipped: no jobs collected');
            return;
        }

        if (env('XXL_JOB_AUTO_REGISTER', true) !== true) {
            LogHelper::info('xxl-job register skipped: XXL_JOB_AUTO_REGISTER=false');
            return;
        }

        $username = (string) env('XXL_JOB_ADMIN_USERNAME', '');
        $password = (string) env('XXL_JOB_ADMIN_PASSWORD', '');
        if ($username === '' || $password === '') {
            LogHelper::warning('xxl-job register skipped: configure XXL_JOB_ADMIN_USERNAME and XXL_JOB_ADMIN_PASSWORD');
            return;
        }

        $appName = (string) (cfg('xxl_job.app_name') ?? env('XXL_JOB_APP_NAME', ''));
        if ($appName === '') {
            LogHelper::warning('xxl-job register skipped: XXL_JOB_APP_NAME is empty');
            return;
        }

        $appTitle = (string) env('XXL_JOB_APP_TITLE', $appName);
        $autoStart = env('XXL_JOB_AUTO_START', false) === true;

        try {
            $client = new XxlJobAdminClient();
            $jobGroupId = $client->resolveJobGroupId($appName, $appTitle);
            LogHelper::info('xxl-job executor group resolved', [
                'app_name' => $appName,
                'app_title' => $appTitle,
                'job_group_id' => $jobGroupId,
            ]);

            foreach ($jobs as $job) {
                try {
                    $client->registerJob($jobGroupId, $job, $autoStart);
                    LogHelper::info('xxl-job job registered', [
                        'handler' => $job['jobHandler'],
                        'cron' => $job['cron'],
                        'auto_start' => $autoStart,
                    ]);
                } catch (Throwable $exception) {
                    LogHelper::error('xxl-job job register failed', [
                        'handler' => $job['jobHandler'],
                        'error' => $exception->getMessage(),
                    ]);
                }
            }
        } catch (Throwable $exception) {
            LogHelper::error('xxl-job register failed', ['error' => $exception->getMessage()]);
        }
    }
}

<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Annotation;

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;

/**
 * 简单示例：#[XxlJobTask(jobDesc: '任务到期提醒', cron: '0/30 * * * * ?', jobHandler: 'taskEndTimeNoticeHandler')]
 * （上面示例30秒一次，规则使用xxl-Job后台的生成，确保正确）.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class XxlJobTask extends AbstractAnnotation
{
    public string $xxlVersion = '3.2.0';  // xxl 版本

    public string $author = '机器注册(adc)';  // 任务负责人

    public string $jobDesc = ''; // 任务描述

    public string $scheduleType = 'CRON'; // 调度类型

    public string $cron = ''; // Cron

    public string $jobHandler = ''; // JobHandler

    public string $jobParam = ''; // 任务参数

    public string $routeStrategy = ''; // 路由策略

    public int $jobTimeout = 0; // 任务超时时间，单位秒

    public int $jobRetry = 0; // 失败重试次数

    public function __construct(
        string $xxlVersion = '3.2.0',
        string $author = '机器注册(adc)',
        string $jobDesc = '',
        string $scheduleType = 'CRON',
        string $cron = '',
        string $jobHandler = '',
        string $jobParam = '',
        string $routeStrategy = '',
        int $jobTimeout = 0,
        int $jobRetry = 0,
    ) {
        $this->xxlVersion = $xxlVersion;
        $this->author = $author;
        $this->jobDesc = $jobDesc;
        $this->scheduleType = $scheduleType;
        $this->cron = $cron;
        $this->jobHandler = $jobHandler;
        $this->jobParam = $jobParam;
        $this->routeStrategy = $routeStrategy;
        $this->jobTimeout = $jobTimeout;
        $this->jobRetry = $jobRetry;
    }
}

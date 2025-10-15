<?php

declare(strict_types=1);

namespace TgkwAdc\Annotation;

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;

/**
 * 请求限流注解
 *
 * 用于对方法进行请求频率限制，防止接口被恶意调用或过载
 *
 * @author ADC Team
 */
#[Attribute(Attribute::TARGET_METHOD)]
class RateRequest extends AbstractAnnotation
{
    /**
     * 限流键名
     *
     * 从请求参数中获取限流标识，多个键用逗号分隔
     * 如果为空，则使用所有请求参数作为限流标识
     */
    public string $rateKey = '';

    /**
     * 等待超时时间（秒）
     *
     * 当达到限流阈值时的等待时间，建议不超过600秒
     * 避免因程序异常导致用户长时间无法操作
     */
    public int $waitTimeout = 10;

    /**
     * 构造函数
     *
     * @param  int  $waitTimeout  等待超时时间（秒），默认10秒
     * @param  string  $rateKey  限流键名，默认为空
     */
    public function __construct(
        int $waitTimeout = 10,
        string $rateKey = '',
    ) {
        $this->waitTimeout = $waitTimeout;
        $this->rateKey = $rateKey;
    }
}

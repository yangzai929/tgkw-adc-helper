<?php

declare(strict_types=1);

namespace TgkwAdc\Annotation;

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;

/**
 * 文本国际化类标识.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class EnumI18nGroup extends AbstractAnnotation
{
    // 文本集合名称
    public string $groupCode;

    // 集合描述
    public string $info;

    /**
     * @param  string  $groupCode  文本集合名称
     * @param  string  $info  错误类的描述
     */
    public function __construct(
        string $groupCode,
        string $info,
    ) {
        $this->groupCode = $groupCode;
        $this->info = $info;
    }
}

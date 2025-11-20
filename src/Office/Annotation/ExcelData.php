<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Office\Annotation;

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;

/**
 * Excel导入导出元数据注解类.
 *
 * 该注解用于标记一个类作为Excel数据传输对象(DTO)，定义该类用于Excel导入导出操作。
 * 通常应用于DTO类上，配合ExcelProperty注解使用。
 *
 * 使用示例：
 * #[ExcelData]
 * class UserImportDto
 * {
 *     // 属性定义
 * }
 */
#[Attribute(Attribute::TARGET_CLASS)]
class ExcelData extends AbstractAnnotation
{
}

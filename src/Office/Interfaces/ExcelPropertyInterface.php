<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Office\Interfaces;

use Closure;
use Hyperf\DbConnection\Model\Model;
use Psr\Http\Message\ResponseInterface;

/**
 * Excel属性接口.
 *
 * 该接口定义了Excel处理类必须实现的方法，包括导入和导出功能。
 * 所有具体的Excel处理实现类都必须实现此接口。
 */
interface ExcelPropertyInterface
{
    /**
     * 导入Excel数据.
     *
     * @param Model $model 数据库模型对象，用于保存导入的数据
     * @param null|Closure $closure 自定义处理闭包，可自定义导入逻辑
     * @return bool 导入是否成功
     */
    public function import(Model $model, ?Closure $closure = null): bool;

    /**
     * 导出Excel数据.
     *
     * @param string $filename 导出的文件名（不含扩展名）
     * @param array|Closure $closure 数据源，可以是数组或闭包函数
     * @return ResponseInterface HTTP响应对象，用于文件下载
     */
    public function export(string $filename, array|Closure $closure): ResponseInterface;
}

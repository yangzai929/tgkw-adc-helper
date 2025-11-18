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

interface ExcelPropertyInterface
{
    public function import(Model $model, ?Closure $closure = null): bool;

    public function export(string $filename, array|Closure $closure): ResponseInterface;
}

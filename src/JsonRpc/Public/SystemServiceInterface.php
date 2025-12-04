<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\JsonRpc\Public;

interface SystemServiceInterface
{
    public function addMenu(array $param): array;

    public function checkAccessPermission(array $param): array;
}

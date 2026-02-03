<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\JsonRpc;

interface BaseCurdServiceInterface
{
    public function columns(): array;

    public function index(array $params): array;

    public function store(array $params): array;

    public function show(array $params): array;

    public function update(array $params): array;

    public function destroy(array $params): array;
}

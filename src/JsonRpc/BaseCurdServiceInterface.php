<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\JsonRpc;

use Psr\Http\Message\ResponseInterface;

interface BaseCurdServiceInterface
{
    public function index(array $params): ResponseInterface;

    public function store(array $params): ResponseInterface;

    public function show(string $id): ResponseInterface;

    public function update(string $id, array $params): ResponseInterface;

    public function destroy(string $id): ResponseInterface;
}

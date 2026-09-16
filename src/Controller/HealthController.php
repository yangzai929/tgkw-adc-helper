<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */
namespace TgkwAdc\Controller;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;

#[Controller]
class HealthController
{
    #[GetMapping(path: '/health')]
    public function index(): array
    {
        return ['status' => 'ok'];
    }
}

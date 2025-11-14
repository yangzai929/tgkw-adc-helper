<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc;

use TgkwAdc\Aspect\RpcServiceAspect;
use TgkwAdc\Listener\MainWorkerStartListener;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
            ],
            'commands' => [
            ],
            'aspects' => [
                RpcServiceAspect::class,
            ],
            'listeners' => [
                MainWorkerStartListener::class,
            ],
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
        ];
    }
}

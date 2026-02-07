<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc;

use TgkwAdc\Aspect\RpcConsumerServiceAspect;
use TgkwAdc\Aspect\RpcProviderServiceAspect;
use TgkwAdc\FileSystem\S3AdapterFactory;
use TgkwAdc\JsonRpc\Public\SystemServiceConsumer;
use TgkwAdc\JsonRpc\Public\SystemServiceInterface;
use TgkwAdc\JsonRpc\User\UserServiceConsumer;
use TgkwAdc\JsonRpc\User\UserServiceInterface;
use TgkwAdc\Listener\MainWorkerStartListener;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                S3AdapterFactory::class => S3AdapterFactory::class, // 修复S3AdapterFactory运行时报错
                UserServiceInterface::class => UserServiceConsumer::class,
                SystemServiceInterface::class => SystemServiceConsumer::class,
            ],
            'commands' => [
            ],
            'aspects' => [
                RpcConsumerServiceAspect::class,
                RpcProviderServiceAspect::class,
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

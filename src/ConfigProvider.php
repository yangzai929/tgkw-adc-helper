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
use TgkwAdc\JsonRpc\Hr\HrServiceConsumer;
use TgkwAdc\JsonRpc\Hr\HrServiceInterface;
use TgkwAdc\JsonRpc\Public\PublicServiceConsumer;
use TgkwAdc\JsonRpc\Public\PublicServiceInterface;
use TgkwAdc\JsonRpc\Public\SystemServiceConsumer;
use TgkwAdc\JsonRpc\Public\SystemServiceInterface;
use TgkwAdc\JsonRpc\User\UserServiceConsumer;
use TgkwAdc\JsonRpc\User\UserServiceInterface;
use TgkwAdc\Listener\MainWorkerStartListener;
use TgkwAdc\Listener\PackageVersionCheckListener;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                S3AdapterFactory::class => S3AdapterFactory::class,            // 修复S3AdapterFactory运行时报错
                UserServiceInterface::class => UserServiceConsumer::class,
                SystemServiceInterface::class => SystemServiceConsumer::class,
                PublicServiceInterface::class => PublicServiceConsumer::class,
            ],
            'commands' => [
            ],
            'aspects' => [
                RpcConsumerServiceAspect::class,
                RpcProviderServiceAspect::class,
            ],
            'listeners' => [
                MainWorkerStartListener::class,
                PackageVersionCheckListener::class,
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

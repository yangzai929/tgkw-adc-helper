<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\FileSystem;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Filesystem\Adapter\LocalAdapterFactory;
use Hyperf\Filesystem\Contract\AdapterFactoryInterface;
use Hyperf\Filesystem\Exception\InvalidArgumentException;
use Hyperf\Filesystem\FilesystemFactory as BaseFilesystemFactory;
use Hyperf\Filesystem\Version;
use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use Psr\Container\ContainerInterface;

class FilesystemFactory extends BaseFilesystemFactory
{
    // 重写BaseFilesystemFactory
    public function __construct(private ContainerInterface $container, private ConfigInterface $config)
    {
    }

    public function get($adapterName): Filesystem
    {

        $default = [
            'default' => 'local',
            'storage' => [
                'local' => [
                    'driver' => LocalAdapterFactory::class,
                    'root' => BASE_PATH . '/runtime',
                ],
            ],
        ];

        $options = cfg('file'); // 从nacos配置中心获取文件系统配置
        if (! $options) {
            $options = $default;
            $adapterName = $default['default'];
        } else {
            $options = json_decode($options, true);
            $adapterName = $options['default'];
        }

        $adapter = $this->getAdapter($options, $adapterName);
        if (Version::isV2()) {
            return new Filesystem($adapter, $options['storage'][$adapterName] ?? []);
        }

        return new Filesystem($adapter, new Config($options['storage'][$adapterName]));
    }

    public function getAdapter($options, $adapterName)
    {
        if (! $options['storage'] || ! $options['storage'][$adapterName]) {
            throw new InvalidArgumentException("file configurations are missing {$adapterName} options");
        }
        /** @var AdapterFactoryInterface $driver */
        $driver = $this->container->get($options['storage'][$adapterName]['driver']);
        return $driver->make($options['storage'][$adapterName]);
    }
}

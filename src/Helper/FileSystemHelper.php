<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Helper;

use DateTime;
use TgkwAdc\FileSystem\FilesystemFactory;

class FileSystemHelper
{
    public static function genFileTempUrl($object_key)
    {
        $nacos = cfg('file'); // 从nacos配置中心获取文件系统配置
        if (! $nacos) {
           return  '暂无文件系统配置';
        }
        $fileConfig = json_decode($nacos, true);
        $adapterName = $fileConfig['default'];
        $factory = make(FilesystemFactory::class);
        $local = $factory->get($adapterName);

        return $local->temporaryUrl($object_key, new DateTime('+10 seconds'));
    }
}

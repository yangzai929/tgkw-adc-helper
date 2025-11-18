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
        $factory = make(FilesystemFactory::class);
        echo 'FilesystemFactory 实例哈希: ' . spl_object_hash($factory) . "\n";

        $local = $factory->get('rustfs');
        echo 'rustfs 客户端实例哈希: ' . spl_object_hash($local) . "\n";

        return $local->temporaryUrl($object_key, new DateTime('+10 seconds'));
    }
}

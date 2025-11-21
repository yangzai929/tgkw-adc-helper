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
use League\Flysystem\FilesystemException;
use League\Flysystem\UnableToWriteFile;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use TgkwAdc\FileSystem\FilesystemFactory;
use TgkwAdc\Helper\Log\LogHelper;

class FileSystemHelper
{
    protected $local;

    protected $urlExpiresAt;

    public function __construct($adapterName = null, $urlExpiresAt = '+10 seconds')
    {
        /** @var FilesystemFactory $factory */
        $factory = make(FilesystemFactory::class);
        $this->local = $factory->get($adapterName);
        $this->urlExpiresAt = $urlExpiresAt;
        return $this;
    }

    public function genFileTempUrl($object_key)
    {
        return $this->local->temporaryUrl($object_key, new DateTime($this->urlExpiresAt));
    }

    public function upload($object_key, $path)
    {
        try {
            $content = file_get_contents($path);
            $this->local->write($object_key, $content);
            return $object_key;
        } catch (FilesystemException|UnableToWriteFile $exception) {
            LogHelper::error('文件上传失败', [
                'object_key' => $object_key,
                'local_path' => $path,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
            return '';
        }
    }

    public function importErrorFileUpload($path)
    {
        try {
            $object_key = env('APP_ENV') . '/import_excel/' . env('APP_NAME') . '/' . Uuid::uuid1() . time() . '.xlsx';
            $content = file_get_contents($path);
            $this->local->write($object_key, $content);
            return $object_key;
        } catch (FilesystemException|UnableToWriteFile $exception) {
            LogHelper::error('文件上传失败', [
                'object_key' => $object_key,
                'local_path' => $path,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
            return '';
        }
    }

    public function genFileName($extension)
    {
        return env('APP_ENV') . '/' . Uuid::uuid1() . '.' . $extension;
    }
}

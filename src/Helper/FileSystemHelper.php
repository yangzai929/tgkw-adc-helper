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
use TgkwAdc\FileSystem\FilesystemFactory;
use TgkwAdc\Helper\Log\LogHelper;

class FileSystemHelper
{
    protected $adapter;

    protected $adapterName;

    public function __construct($adapterName = null)
    {
        /** @var FilesystemFactory $factory */
        $factory = make(FilesystemFactory::class);
        $this->adapter = $factory->get($adapterName);
        $this->adapterName = $factory->adapterName;
        return $this;
    }

    public function getAdapter()
    {
        return $this->adapter;
    }

    public function getAdapterName()
    {
        return $this->adapterName;
    }

    public function genFileTempUrl($object_key, string $expiresAt = '+10 seconds')
    {
        return $this->adapter->temporaryUrl($object_key, new DateTime($expiresAt));
    }

    public function upload($object_key, $path)
    {
        try {
            $content = file_get_contents($path);
            $this->adapter->write($object_key, $content);
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
            $this->adapter->write($object_key, $content);
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
        $ilename = str_replace('-', '', Uuid::uuid1());
        return env('APP_ENV') . '/' . $ilename . '.' . $extension;
    }
}

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
    /**
     * 文件系统适配器实例.
     * @var mixed
     */
    protected $adapter;

    /**
     * 适配器名称.
     * @var null|string
     */
    protected $adapterName;

    /**
     * 文件系统工厂实例.
     * @var FilesystemFactory
     */
    protected $factory;

    public function __construct($adapterName = null)
    {
        /** @var FilesystemFactory $factory */
        $factory = make(FilesystemFactory::class);
        $this->adapter = $factory->get($adapterName);
        $this->adapterName = $factory->adapterName;
    }

    public function getAdapter()
    {
        return $this->adapter;
    }

    public function getAdapterName()
    {
        return $this->adapterName;
    }

    /**
     * 重新设置适配器（显式修改状态）.
     *
     * @return $this
     */
    public function setAdapter(?string $adapterName = null)
    {
        $this->adapter = $this->factory->get($adapterName);
        $this->adapterName = $this->factory->adapterName;

        return $this;
    }

    public function genFileTempUrl($object_key, string $expiresAt = '+1 days')
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
        $ilename = str_replace('-', '', (string) Uuid::uuid1());
        return env('APP_ENV') . '/' . $ilename . '.' . $extension;
    }
}

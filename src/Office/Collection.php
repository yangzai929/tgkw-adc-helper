<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Office;

use Closure;
use Hyperf\Codec\Json;
use Hyperf\DbConnection\Model\Model;
use Psr\Http\Message\ResponseInterface;
use TgkwAdc\Office\Excel\PhpOffice;
use TgkwAdc\Office\Excel\XlsWriter;

/**
 * Excel操作集合类.
 *
 * 该类继承自Hyperf的Collection类，提供了Excel文件的导入和导出功能。
 * 支持根据配置自动选择使用xlswriter或phpoffice驱动来处理Excel文件。
 */
class Collection extends \Hyperf\Collection\Collection
{
    /**
     * 导出Excel文件.
     *
     * @param string $dto DTO类名，用于获取Excel列的配置信息
     * @param string $filename 导出的文件名（不含扩展名）
     * @param null|array|Closure $closure 数据源，可以是数组或闭包函数
     * @param array $extra 额外配置参数
     * @param bool $isDemo 是否为示例模式，用于导出示例模板
     * @param array $infos 额外信息数组
     * @param int $orgId 组织ID，用于多租户场景
     * @return ResponseInterface HTTP响应对象，用于文件下载
     */
    public function export(string $dto, string $filename, array|Closure|null $closure = null, array $extra = [], bool $isDemo = false, array $infos = [], int $orgId = 0): ResponseInterface
    {
        // 获取配置的Excel驱动，默认为auto（自动选择）
        $excelDrive = \Hyperf\Config\config('excel.drive', 'auto');

        // 根据配置选择驱动
        if ($excelDrive === 'auto') {
            // 自动选择：如果安装了xlswriter扩展则使用xlswriter，否则使用phpoffice
            $driver = extension_loaded('xlswriter') ? 'xlswriter' : 'phpoffice';
        } else {
            // 使用配置指定的驱动
            $driver = strtolower($excelDrive);
        }

        // 处理数据源
        $data = is_null($closure) ? $this->toArray() : $closure;

        // 根据选择的驱动创建对应的Excel处理对象并执行导出
        switch ($driver) {
            case 'xlswriter':
                $excel = new XlsWriter($dto, $extra, $isDemo, $orgId, $infos);
                return $excel->export($filename, $data, null, $isDemo, $orgId, $infos);
            case 'phpoffice':
            default:
                $excel = new PhpOffice($dto);
                return $excel->export($filename, $data, $isDemo, $orgId);
        }
    }

    /**
     * 导入Excel文件.
     *
     * @param string $dto DTO类名，用于获取Excel列的配置信息
     * @param Model $model 数据库模型对象，用于保存导入的数据
     * @param null|Closure $closure 自定义处理闭包，可自定义导入逻辑
     * @param array $extra 额外配置参数
     * @param int $orgId 组织ID，用于多租户场景
     * @return bool 导入是否成功
     */
    public function import(string $dto, Model $model, ?Closure $closure = null, array $extra = [], int $orgId = 0): bool
    {
        // 获取配置的Excel驱动，默认为auto（自动选择）
        $excelDrive = \Hyperf\Config\config('excel.drive', 'auto');

        // 根据配置选择驱动和创建对应的Excel处理对象
        if ($excelDrive === 'auto') {
            // 自动选择：如果安装了xlswriter扩展则使用xlswriter，否则使用phpoffice
            $excel = extension_loaded('xlswriter') ? new XlsWriter($dto, $extra, false, $orgId) : new PhpOffice($dto);
        } else {
            // 使用配置指定的驱动
            $excel = $excelDrive === 'xlsWriter' ? new XlsWriter($dto, $extra, false, $orgId) : new PhpOffice($dto);
        }

        // 执行导入操作
        return $excel->import($model, $closure, $orgId);
    }

    /**
     * 写错误的缓存.
     *
     * 用于存储导入过程中出现的错误信息，便于后续查看和处理
     *
     * @param string $keyPrefix 缓存前缀
     * @param string $dto Dto类
     * @param string $filename 错误文件名
     * @param array $data 错误数据
     * @param int $orgId 机构ID
     * @param int $ttl 缓存时间，默认5分钟
     * @return string 缓存键名
     */
    public function setImportErrorCache(string $keyPrefix, string $dto, string $filename, array $data, int $orgId = 0, int $ttl = 300): string
    {
        // 生成唯一的缓存键名
        $errorRedisKey = $keyPrefix . ':' . $orgId . ':' . uniqid();

        // 构造缓存数据
        $cacheData = [
            'org_id' => $orgId,
            'dto' => $dto,
            'data' => $data,
            'filename' => $filename,
        ];

        // 将错误数据存储到Redis中
        redis()->set($errorRedisKey, Json::encode($cacheData), $ttl);

        // 返回缓存键名
        return $errorRedisKey;
    }
}

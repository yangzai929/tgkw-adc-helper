<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Office;

use Exception;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Psr\Http\Message\ResponseInterface;
use TgkwAdc\Constants\I18n\Excel\ExcelCommonI18n;
use TgkwAdc\Helper\Intl\I18nHelper;
use TgkwAdc\Office\Annotation\ExcelProperty;
use TgkwAdc\Office\Interfaces\ModelExcelInterface;
use Vtiful\Kernel\Format;

/**
 * Excel处理抽象基类.
 *
 * 该类为Excel导入导出功能提供基础支撑，定义了通用的属性和方法。
 * 具体的实现由子类PhpOffice和XlsWriter完成。
 */
abstract class Excel
{
    /**
     * Excel属性注解名称常量.
     */
    public const ANNOTATION_NAME = 'TgkwAdc\Office\Annotation\ExcelProperty';

    /**
     * 注解元数据.
     */
    protected ?array $annotationMate;

    /**
     * 属性配置数组.
     */
    protected array $property = [];

    /**
     * 字典数据.
     */
    protected array $dictData = [];

    /**
     * 示例值数组.
     */
    protected array $demoValue = [];

    /**
     * 是否为示例模式.
     */
    protected bool $isDemo = false;

    /**
     * 当前语言
     */
    protected string $nowLang = '';

    /**
     * 组织ID.
     */
    protected int $orgId = 0;

    /**
     * 构造函数.
     *
     * @param string $dto DTO类名，用于获取Excel列的配置信息
     * @param array $extraData 额外数据配置
     * @param bool $isDemo 是否为示例模式
     * @param int $orgId 组织ID
     * @param array $infos 额外信息数组
     * @throws Exception 当DTO类未实现ModelExcelInterface接口时抛出异常
     */
    public function __construct(string $dto, array $extraData = [], bool $isDemo = false, int $orgId = 0, array $infos = [])
    {
        // 检查DTO类是否实现了ModelExcelInterface接口
        if (! (new $dto()) instanceof ModelExcelInterface) {
            throw new Exception('Dto does not implement an interface of the MineModelExcel', 400);
        }

        // 创建DTO实例并获取字典数据
        $dtoObject = new $dto();
        if (method_exists($dtoObject, 'dictData')) {
            $this->dictData = $dtoObject->dictData();
        }

        // 设置组织ID
        $this->orgId = $orgId;

        // 通过注解收集器获取DTO类的注解信息
        $this->annotationMate = AnnotationCollector::get($dto);

        // 处理额外数据配置
        if (! empty($extraData)) {
            if (! empty($this->annotationMate['_c'])) {
                // 计算起始索引位置
                if (empty($this->annotationMate['_p'])) {
                    $startIndex = -1;
                } else {
                    $startIndex = count($this->annotationMate['_p']) - 1;
                }

                // 遍历额外数据并添加到注解元数据中
                foreach ($extraData as $key => $value) {
                    ++$startIndex;
                    if (empty($this->annotationMate['_p'][$value['key']][self::ANNOTATION_NAME])) {
                        // 创建新的ExcelProperty对象
                        $dataObj = new ExcelProperty(
                            value: $value['fields_name'],
                            index: $startIndex,
                            demo: $value['fields_demo'] ?? '',
                            tip: $value['fields_tip'] ?? '',
                            i18nValue: $value['i18n_fields_name']['i18n_value'] ?? [],
                            i18nDemo: $value['i18n_fields_demo']['i18n_value'] ?? [],
                            i18nTip: $value['i18n_fields_tip']['i18n_value'] ?? [],
                            width: 20,
                            align: 'left',
                            required: (bool) $value['fill'],
                            dictName: $value['dictName'] ?? '',
                            dictData: $value['dictData'] ?? [],
                        );
                        $this->annotationMate['_p'][$value['key']][self::ANNOTATION_NAME] = $dataObj;
                    } else {
                        // 导入判断必填，可以拓展为其他字段的必填属性
                        if (isset($value['required'])) {
                            $this->annotationMate['_p'][$value['key']][self::ANNOTATION_NAME]->required = $value['required'];
                        }
                        // 更新 dictData（如果提供了）
                        if (isset($value['dictData']) && ! empty($value['dictData'])) {
                            $this->annotationMate['_p'][$value['key']][self::ANNOTATION_NAME]->dictData = $value['dictData'];
                        }
                    }
                }
            }
        }

        // 拼接导入结果字段
        if (! $isDemo && empty($infos['is_export'])) {
            $i18nResult = ExcelCommonI18n::IMPORT_RESULT->genI18nTxt();
            $this->annotationMate['_p']['result'][self::ANNOTATION_NAME] = new ExcelProperty(
                value: '导入结果',
                index: count($this->annotationMate['_p']),
                i18nValue: $i18nResult['i18n_value'],
                width: 25,
                align: 'left',
                required: false,
            );
        }

        // 解析属性配置
        $this->parseProperty();
    }

    /**
     * 获取属性配置.
     *
     * @return array 属性配置数组
     */
    public function getProperty(): array
    {
        return $this->property;
    }

    /**
     * 获取注解信息.
     *
     * @return array 注解信息数组
     */
    public function getAnnotationInfo(): array
    {
        return $this->annotationMate;
    }

    /**
     * 解析属性配置.
     *
     * 该方法将注解信息转换为属性配置数组，用于Excel处理
     *
     * @throws Exception 当DTO注解信息为空时抛出异常
     */
    protected function parseProperty(): void
    {
        // 检查注解信息是否为空
        if (empty($this->annotationMate) || ! isset($this->annotationMate['_c'])) {
            throw new Exception('Dto annotation info is empty', 400);
        }

        // 获取当前语言
        $this->nowLang = I18nHelper::getNowLang();

        // 遍历注解属性并构建属性配置数组
        foreach ($this->annotationMate['_p'] as $name => $mate) {
            // 获取字段名称（支持国际化）
            $value = $mate[self::ANNOTATION_NAME]->i18nValue[$this->nowLang] ?? $mate[self::ANNOTATION_NAME]->value;

            // 获取字段提示信息（支持国际化）
            $tip = $mate[self::ANNOTATION_NAME]->i18nTip[$this->nowLang] ?? $mate[self::ANNOTATION_NAME]->tip;

            // 英文、日语环境下，宽度放大0.4倍
            $width = ! empty($mate[self::ANNOTATION_NAME]->width) ? (in_array($this->nowLang, ['en', 'ja']) ? intval($mate[self::ANNOTATION_NAME]->width * 1.4) : $mate[self::ANNOTATION_NAME]->width) : null;

            // 构建属性配置
            $this->property[$mate[self::ANNOTATION_NAME]->index] = [
                'name' => $name,
                'value' => $value,
                'tip' => $tip,
                'width' => $width,
                'height' => $mate[self::ANNOTATION_NAME]->height ?? null,
                'align' => $mate[self::ANNOTATION_NAME]->align ?? null,
                'headColor' => Format::COLOR_WHITE,
                'headBgColor' => $mate[self::ANNOTATION_NAME]->required ? Format::COLOR_RED : 0x5A5A5A,
                'headHeight' => $mate[self::ANNOTATION_NAME]->headHeight ?? null,
                'color' => $mate[self::ANNOTATION_NAME]->color ?? null,
                'bgColor' => $mate[self::ANNOTATION_NAME]->bgColor ?? null,
                'dictName' => $mate[self::ANNOTATION_NAME]->dictName ?? '',
                'dictData' => $mate[self::ANNOTATION_NAME]->dictData ?? [],
                'required' => $mate[self::ANNOTATION_NAME]->required ?? false,
                'dateTime' => $mate[self::ANNOTATION_NAME]->dateTime ?? null,
            ];

            // 获取示例值（支持国际化）
            $this->demoValue[$name] = $mate[self::ANNOTATION_NAME]->i18nDemo[$this->nowLang] ?? $mate[self::ANNOTATION_NAME]->demo;
        }

        // 按索引排序属性配置
        ksort($this->property);
    }

    /**
     * 下载excel文件.
     *
     * @param string $filename 文件名
     * @param string $content 文件内容
     * @return ResponseInterface HTTP响应对象
     */
    protected function downloadExcel(string $filename, string $content): ResponseInterface
    {
        return $response = context_get(ResponseInterface::class)
            ->withHeader('Server', 'TgkwAdc')
            ->withHeader('access-control-expose-headers', 'content-disposition')
            ->withHeader('content-description', 'File Transfer')
            ->withHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->withHeader('content-disposition', "attachment; filename={$filename}; filename*=UTF-8''" . rawurlencode($filename))
            ->withHeader('content-transfer-encoding', 'binary')
            ->withHeader('pragma', 'public')
            ->withBody(new SwooleStream($content));
    }

    /**
     * 获取 Excel 列索引.
     *
     * @param int $columnIndex 列索引（从0开始）
     * @return string Excel列标识符（如A、B、AA等）
     */
    protected function getColumnIndex(int $columnIndex = 0): string
    {
        if ($columnIndex < 26) {
            return chr(65 + $columnIndex);
        }
        if ($columnIndex < 702) {
            return chr(64 + intval($columnIndex / 26)) . chr(65 + $columnIndex % 26);
        }
        return chr(64 + intval(($columnIndex - 26) / 676)) . chr(65 + intval((($columnIndex - 26) % 676) / 26)) . chr(65 + $columnIndex % 26);
    }

    /**
     * 获取 runtime 目录路径.
     *
     * @return string runtime目录路径
     */
    protected static function getRuntimePath(): string
    {
        return rtrim(BASE_PATH, '/\\') . '/runtime';
    }
}

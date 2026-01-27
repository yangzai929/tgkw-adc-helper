<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Office\Excel;

use Closure;
use Exception;
use Hyperf\DbConnection\Model\Model;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use TgkwAdc\Constants\Code\CommonCode;
use TgkwAdc\Constants\I18n\Excel\ExcelCommonI18n;
use TgkwAdc\Exception\BusinessException;
use TgkwAdc\Helper\StrHelper;
use TgkwAdc\Helper\XlsWriterHelper;
use TgkwAdc\Office\Excel;
use TgkwAdc\Office\Interfaces\ExcelPropertyInterface;
use Vtiful\Kernel\Format;
use Vtiful\Kernel\Validation;

/**
 * 基于 xlswriter 扩展的 Excel 工具.
 *
 * - 支持导入: 通过读取上传文件并转换为模型可用的数据数组
 * - 支持导出: 根据定义的字段属性生成带样式、校验、提示的 Excel
 */
class XlsWriter extends Excel implements ExcelPropertyInterface
{
    /**
     * 从请求对象中解析 Excel 内容并返回 sheet 数据.
     *
     * @param mixed $request 可以是实现了 file() 方法的请求对象
     *
     * @return array sheet 内容二维数组
     */
    public static function getSheetData(mixed $request): array
    {
        // 获取上传文件
        $file = $request->file('file');

        // 生成临时文件名
        $tempFileName = 'import_' . time() . '.' . $file->getExtension();
        $runtimePath = static::getRuntimePath();
        $tempFilePath = $runtimePath . '/' . $tempFileName;

        // 将文件内容写入临时文件
        file_put_contents($tempFilePath, $file->getStream()->getContents());

        // 使用xlswriter打开文件并获取sheet数据
        $xlsxObject = new \Vtiful\Kernel\Excel(['path' => $runtimePath . '/']);
        return $xlsxObject->openFile($tempFileName)->openSheet()->getSheetData();
    }

    /**
     * 导入数据.
     *
     * @param Model $model 目标模型实例，用于持久化
     * @param null|Closure $closure 自定义处理闭包，若提供则由闭包接管导入逻辑
     * @param int $orgId 预留组织维度参数
     */
    public function import(Model $model, ?Closure $closure = null, int $orgId = 0): bool
    {
        // 获取请求对象
        $request = container_get(RequestInterface::class);

        // 检查是否有上传文件
        if ($request->hasFile('file')) {
            // 获取上传文件
            $file = $request->file('file');

            // 生成临时文件名
            $tempFileName = 'import_' . time() . '_' . mt_rand(10000, 99999) . '.' . $file->getExtension();
            $runtimePath = static::getRuntimePath();
            $tempFilePath = $runtimePath . '/' . $tempFileName;

            // 将上传流写入运行目录临时文件
            file_put_contents($tempFilePath, $file->getStream()->getContents());

            // 创建xlswriter对象
            $xlsxObject = new \Vtiful\Kernel\Excel(['path' => $runtimePath . '/']);

            // 统一设置为字符串类型
            $setTypeArr = [];
            for ($i = 0; $i < count($this->property); ++$i) {
                $setTypeArr[] = \Vtiful\Kernel\Excel::TYPE_STRING;
            }

            // 读取sheet数据
            $data = $xlsxObject->openFile($tempFileName)->openSheet()->setType($setTypeArr)->getSheetData();
            unset($data[0], $data[1]);

            // 日期转换等辅助方法
            $xlsWriterHelper = new XlsWriterHelper();

            $importData = [];

            // 遍历数据行
            foreach ($data as $item) {
                $tmp = [];
                $errorMsg = '';
                $emptyRow = true;

                // 遍历每列数据
                foreach ($item as $key => $value) {
                    $value = StrHelper::mb_trim((string) $value);

                    // 判断是否是空行
                    if ($emptyRow && ! empty($value)) {
                        $emptyRow = false;
                    }

                    $tmpProperty = $this->property[$key];

                    // 不存在的值则跳过
                    if (empty($tmpProperty)) {
                        continue;
                    }

                    $tmp[$tmpProperty['name']] = $value;

                    // 判断必填字段
                    if (empty($errorMsg) && $tmpProperty['required'] && $value === '') {
                        $errorMsg = CommonCode::PARAMS_EMPTY_WITH_FIELD->genI18nMsg(['field' => $tmpProperty['value']], true, $this->nowLang);
                    }

                    // 判断日期时间字段
                    if (empty($errorMsg) && $tmpProperty['dateTime'] && $value != '') {
                        $realDateTime = $xlsWriterHelper->formatDate($value, $tmpProperty['dateTime']);
                        if (empty($realDateTime)) {
                            $errorMsg = CommonCode::PARAMS_WRONG_WITH_FIELD->genI18nMsg(['field' => $tmpProperty['value']], true, $this->nowLang);
                        }
                        $tmp[$tmpProperty['name']] = $realDateTime;
                    }

                    // 判断字典值
                    if (empty($errorMsg) && ! empty($tmpProperty['dictNameArr'])) {
                        if (in_array($value, $tmpProperty['dictNameArr'])) {
                            $tmp[$tmpProperty['name']] = array_search($value, $tmpProperty['dictNameArr']);
                        } elseif ($tmpProperty['required']) {
                            $errorMsg = CommonCode::PARAMS_WRONG_WITH_FIELD->genI18nMsg(['field' => $tmpProperty['value']], true, $this->nowLang);
                        }
                    }

                    // 判断字典数组
                    if (empty($errorMsg) && ! empty($tmpProperty['dictData'])) {
                        if (in_array($value, $tmpProperty['dictData'])) {
                            $tmp[$tmpProperty['name']] = array_search($value, $tmpProperty['dictData']);
                        } elseif ($tmpProperty['required']) {
                            $errorMsg = CommonCode::PARAMS_WRONG_WITH_FIELD->genI18nMsg(['field' => $tmpProperty['value']], true, $this->nowLang);
                        }
                    }
                }

                // 跳过空行
                if ($emptyRow) {
                    continue;
                }

                // 添加导入结果信息
                $tmp['result'] = $errorMsg;
                $importData[] = $tmp;
            }

            // 如果提供了自定义处理闭包，则执行闭包逻辑
            if ($closure instanceof Closure) {
                return $closure($model, $importData);
            }

            try {
                // 默认流程：逐条写入数据库
                foreach ($importData as $item) {
                    $model::create($item);
                }
                @unlink($tempFilePath);
            } catch (Exception $e) {
                @unlink($tempFilePath);
                throw new Exception($e->getMessage());
            }
            return true;
        }
        return false;
    }

    /**
     * 导出 Excel 并以下载形式返回响应.
     *
     * @param string $filename 输出文件名（不含扩展名）
     * @param array|Closure $closure 提供原始数据或返回数据的闭包
     * @param null|Closure $callbackData 数据行回调，可对每行做最后加工
     * @param bool $isDemo 是否导出示例行
     * @param int $orgId 预留组织维度参数
     * @param array $infos 额外配置，如导出标记、提示信息等
     */
    public function export(string $filename, array|Closure $closure, ?Closure $callbackData = null, bool $isDemo = false, int $orgId = 0, array $infos = []): \Psr\Http\Message\ResponseInterface
    {
        // 设置文件名
        $filename .= '.xlsx';

        // 获取数据：数组或执行闭包
        is_array($closure) ? $data = &$closure : $data = $closure();

        // 对齐方式映射
        $aligns = [
            'left' => Format::FORMAT_ALIGN_LEFT,
            'center' => Format::FORMAT_ALIGN_CENTER,
            'right' => Format::FORMAT_ALIGN_RIGHT,
        ];

        // 初始化列配置数组
        $columnName = [];
        $columnField = [];
        $columnTip = [];
        $validationField = [];
        $properties = array_values($this->property);

        // 检查属性配置是否为空
        if (empty($properties)) {
            throw new BusinessException(CommonCode::EXPORT_FAILED);
        }

        // 组装列配置: 名称/字段/提示
        foreach ($properties as $item) {
            $columnName[] = $item['value'];
            $columnField[] = $item['name'];

            if (! empty($item['tip'])) {
                $columnTip[] = [
                    'value' => $item['value'],
                    'tip' => $item['tip'],
                ];
            }
        }

        // 生成临时文件名
        $tempFileName = 'export_' . time() . '.xlsx';
        $runtimePath = static::getRuntimePath();

        // 创建xlswriter对象
        $xlsxObject = new \Vtiful\Kernel\Excel(['path' => $runtimePath . '/']);
        $fileObject = $xlsxObject->fileName($tempFileName)->header($columnName);
        $columnFormat = new Format($fileObject->getHandle());
        $rowFormat = new Format($fileObject->getHandle());

        // 设置列格式
        for ($i = 0; $i < count($columnField); ++$i) {
            $currentProperty = $properties[$i] ?? [];
            $columnIndex = $this->getColumnIndex($i);
            $fileObject->setColumn(
                sprintf('%s:%s', $columnIndex, $columnIndex),
                $currentProperty['width'] ?? mb_strlen($columnName[$i]) * 5,
                $columnFormat->align($currentProperty['align'] ? $aligns[$currentProperty['align']] : $aligns['left'])
                    ->background($currentProperty['bgColor'] ?? Format::COLOR_WHITE)
                    ->border(Format::BORDER_THIN)
                    ->fontColor($currentProperty['color'] ?? Format::COLOR_BLACK)
                    ->toResource()
            );

            // 判断校验字段
            if (! empty($currentProperty['dictNameArr'])) {
                $validationField[$i] = array_values($currentProperty['dictNameArr']);
            } elseif (! empty($currentProperty['dictData'])) {
                $validationField[$i] = array_values($currentProperty['dictData']);
            }
        }

        $fileObject->setRow(
            sprintf('A1:%s1', $this->getColumnIndex(count($columnField))),
            $properties[0]['headHeight'] ?? 24,
            $rowFormat->bold()->toResource()
        );

        // 表头加样式
        if (! empty($infos['is_export'])) {
            $fileObject->setRow(
                sprintf('A1:%s1', $this->getColumnIndex(count($columnField))),
                $properties[0]['headHeight'] ?? 24,
                $rowFormat->bold()
                    ->align(Format::FORMAT_ALIGN_CENTER, Format::FORMAT_ALIGN_VERTICAL_CENTER)
                    ->background(0x4AC1FF)
                    ->fontColor(Format::COLOR_BLACK)
                    ->toResource()
            );
        }

        // 表内容加样式 - 为每列数据行设置对齐
        $dataLength = max(count($data), 50);
        for ($i = 0; $i < count($columnField); ++$i) {
            $currentProperty = $properties[$i] ?? [];
            $columnIndex = $this->getColumnIndex($i);
            $dataAlign = $currentProperty['align'] ? $aligns[$currentProperty['align']] : $aligns['left'];
            $fileObject->setRow(
                sprintf('%s2:%s%s', $columnIndex, $columnIndex, $dataLength + 2),
                $properties[0]['height'] ?? 24,
                (new Format($fileObject->getHandle()))
                    ->align($dataAlign, Format::FORMAT_ALIGN_VERTICAL_CENTER)
                    ->toResource()
            );
        }

        //        // 设置表头样式
        if (empty($infos['is_export'])) {
            for ($i = 0; $i < count($columnField); ++$i) {
                $currentProperty = $properties[$i] ?? [];
                $fileObject->insertText(
                    1,
                    $i,
                    $columnName[$i],
                    null,
                    (new Format($fileObject->getHandle()))
                        ->bold()
                        ->align(Format::FORMAT_ALIGN_CENTER, Format::FORMAT_ALIGN_VERTICAL_CENTER)
                        ->background($currentProperty['headBgColor'] ?? 0x4AC1FF)
                        ->fontColor($currentProperty['headColor'] ?? Format::COLOR_BLACK)
                        ->toResource()
                );
            }
        }

        $exportData = [];
        if (empty($infos['is_export'])) {
            $exportData = [
                [],
            ];
        }

        // 构造导出行数据
        foreach ($data as $item) {
            $yield = [];
            if ($callbackData) {
                $item = $callbackData($item);
            }
            foreach ($this->property as $property) {
                foreach ($item as $name => $value) {
                    if ($property['name'] == $name) {
                        if (! empty($property['dictNameArr'])) {
                            $yield[] = $property['dictNameArr'][$value] ?? '';
                        } elseif (! empty($property['dictData'])) {
                            $yield[] = $property['dictData'][$value] ?? '';
                        } elseif (! empty($property['path'])) {
                            $yield[] = data_get($item, $property['path']);
                        } else {
                            $yield[] = $value;
                        }
                        break;
                    }
                }
            }
            $exportData[] = $yield;
        }

        // 添加示例数据
        if (! empty($this->demoValue) && $isDemo) {
            $yieldData = [];
            foreach ($this->property as $property) {
                foreach ($this->demoValue as $key => $value) {
                    if ($property['name'] == $key) {
                        $yieldData[] = $value;
                        break;
                    }
                }
            }
            if (! empty($yieldData)) {
                $exportData[] = $yieldData;
            }
        }

        // 添加提示信息
        if (empty($infos['is_export'])) {
            $tipArr = [
                ExcelCommonI18n::TIP->genI18nTxt(returnNowLang: true),
                '1. ' . ExcelCommonI18n::DONT_MODIFY_TABLE_STRUCTURE->genI18nTxt(returnNowLang: true),
                '2. ' . ExcelCommonI18n::RED_FIELDS_REQUIRED->genI18nTxt(returnNowLang: true),
            ];
            foreach ($columnTip as $item) {
                $tipArr[] = count($tipArr) . '. ' . $item['value'] . ': ' . $item['tip'];
            }
            if (! empty($infos['tips'])) {
                foreach ($infos['tips'] as $infoTip) {
                    $tipArr[] = count($tipArr) . '. ' . $infoTip['value'] . ': ' . $infoTip['tip'];
                }
            }
            $fileObject->mergeCells(sprintf('A1:%s1', $this->getColumnIndex(count($columnField) - 1)), implode(PHP_EOL, $tipArr));
            $fileObject->setRow(
                'A1:A1',
                20 * count($tipArr),
                (new Format($fileObject->getHandle()))->align(Format::FORMAT_ALIGN_LEFT, Format::FORMAT_ALIGN_VERTICAL_TOP)->wrap()->toResource()
            );
        }

        // 获取响应对象
        $response = container_get(ResponseInterface::class);

        // 写入数据
        $filePath = $fileObject->data($exportData);

        // 添加数据验证
        foreach ($validationField as $key => $item) {
            $validation = new Validation();
            $validation = $validation->validationType(Validation::TYPE_LIST)->valueList($item);
            $forRows = max(count($exportData), 22);
            $column = $this->getColumnIndex($key);
            for ($i = 3; $i < $forRows; ++$i) {
                $filePath = $filePath->validation($column . $i, $validation->toResource());
            }
        }

        // 输出文件
        $filePath = $filePath->output();

        // 下载文件
        $response->download($filePath, $filename);

        ob_start();
        if (copy($filePath, 'php://output') === false) {
            throw new BusinessException(CommonCode::EXPORT_FAILED);
        }
        $res = $this->downloadExcel($filename, ob_get_contents());
        ob_end_clean();

        @unlink($filePath);

        return $res;
    }
}

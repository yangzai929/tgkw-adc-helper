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
use Generator;
use Hyperf\DbConnection\Model\Model;
use Hyperf\HttpServer\Contract\RequestInterface;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use TgkwAdc\Constants\I18n\Excel\ExcelCommonI18n;
use TgkwAdc\Office\Excel;
use TgkwAdc\Office\Interfaces\ExcelPropertyInterface;
use Throwable;

/**
 * 基于 PhpSpreadsheet 的 Excel 读写实现.
 *
 * 该实现提供统一的导入导出能力，并支持为
 * 列配置校验、提示信息、颜色等扩展属性。
 */
class PhpOffice extends Excel implements ExcelPropertyInterface
{
    /**
     * 从上传的 Excel 文件导入数据.
     *
     * @param Model $model 对应的 ORM 模型，用于写入数据
     * @param Closure $closure 自定义导入逻辑，接收 (Model $model, array $rows)
     * @param int $orgId 业务使用的组织 ID，默认 0
     *
     * @throws Exception
     */
    public function import(Model $model, ?Closure $closure = null, int $orgId = 0): bool
    {
        // 获取请求对象
        $request = container_get(RequestInterface::class);
        $data = [];

        // 检查是否有上传文件
        if ($request->hasFile('file')) {
            // 获取上传的文件
            $file = $request->file('file');

            // 生成临时文件名，避免直接在内存中处理大文件
            $tempFileName = 'import_' . time() . '.' . $file->getExtension();
            $runtimePath = static::getRuntimePath();
            $tempFilePath = $runtimePath . '/' . $tempFileName;

            // 将文件内容写入临时文件
            file_put_contents($tempFilePath, $file->getStream()->getContents());

            try {
                // 使用 PhpSpreadsheet 自动识别格式后读取
                $reader = IOFactory::createReader(IOFactory::identify($tempFilePath));
                $reader->setReadDataOnly(true);
                $sheet = $reader->load($tempFilePath);

                // 确定结束列
                $endCell = isset($this->property) ? $this->getColumnIndex(count($this->property)) : null;

                // 遍历Excel行数据（从第2行开始，第1行为表头）
                foreach ($sheet->getActiveSheet()->getRowIterator(2) as $row) {
                    $temp = [];

                    // 遍历每行的单元格
                    foreach ($row->getCellIterator('A', $endCell) as $index => $item) {
                        // 将Excel列索引转换为属性索引
                        $propertyIndex = ord($index) - 65;

                        // 将 Excel 列映射到 property 配置的字段名
                        if (isset($this->property[$propertyIndex])) {
                            $temp[$this->property[$propertyIndex]['name']] = $item->getFormattedValue();
                        }
                    }

                    // 如果当前行有数据则添加到结果数组
                    if (! empty($temp)) {
                        $data[] = $temp;
                    }
                }

                // 删除临时文件
                unlink($tempFilePath);
            } catch (Throwable $e) {
                // 发生异常时删除临时文件并重新抛出异常
                unlink($tempFilePath);
                throw new Exception($e->getMessage());
            }
        } else {
            // 没有上传文件则返回false
            return false;
        }

        // 如果提供了自定义处理闭包，则执行闭包逻辑
        if ($closure instanceof Closure) {
            return $closure($model, $data);
        }

        // 默认处理逻辑：逐条创建模型记录
        foreach ($data as $datum) {
            $model::create($datum);
        }

        return true;
    }

    /**
     * 导出 Excel 数据并返回下载响应.
     *
     * @param string $filename 文件名（无需扩展名）
     * @param array|Closure $closure 数据来源，数组或生成数据的闭包
     * @param bool $isDemo 是否导出示例模板，开启后强制展示提示
     * @param int $orgId 组织 ID，占位参数以兼容接口
     * @param array $infos 扩展信息，如额外提示
     */
    public function export(string $filename, array|Closure $closure, bool $isDemo = false, int $orgId = 0, array $infos = []): ResponseInterface
    {
        // 创建Spreadsheet对象
        $spread = new Spreadsheet();
        $sheet = $spread->getActiveSheet();
        $filename .= '.xlsx';

        // 获取数据：数组或执行闭包
        is_array($closure) ? $data = &$closure : $data = $closure();

        // 计算提示行数，如果有提示信息则从第2行开始，否则从第1行开始
        $headerRow = 1;
        $dataStartRow = 2;
        $hasTipRow = false;

        // 添加提示信息行
        if (empty($infos['is_export']) || $isDemo) {
            // 构造提示信息数组
            $tipArr = [
                ExcelCommonI18n::TIP->genI18nTxt(returnNowLang: true),
                '1. ' . ExcelCommonI18n::DONT_MODIFY_TABLE_STRUCTURE->genI18nTxt(returnNowLang: true),
                '2. ' . ExcelCommonI18n::RED_FIELDS_REQUIRED->genI18nTxt(returnNowLang: true),
            ];

            // 收集字段提示信息
            $columnTip = [];
            foreach ($this->property as $item) {
                if (! empty($item['tip'])) {
                    $columnTip[] = [
                        'value' => $item['value'],
                        'tip' => $item['tip'],
                    ];
                }
            }

            // 添加字段提示信息
            foreach ($columnTip as $item) {
                $tipArr[] = count($tipArr) . '. ' . $item['value'] . ': ' . $item['tip'];
            }

            // 添加额外提示信息
            if (! empty($infos['tips'])) {
                foreach ($infos['tips'] as $infoTip) {
                    $tipArr[] = count($tipArr) . '. ' . $infoTip['value'] . ': ' . $infoTip['tip'];
                }
            }

            // 如果是演示模式或有多条提示，则显示提示行
            if ($isDemo || count($tipArr) > 3) {
                $hasTipRow = true;
                $headerRow = 2;
                $dataStartRow = 3;

                // 合并提示行单元格
                $lastColumn = $this->getColumnIndex(count($this->property) - 1);
                $sheet->mergeCells('A1:' . $lastColumn . '1');
                $sheet->setCellValue('A1', implode(PHP_EOL, $tipArr));
                $sheet->getStyle('A1')->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
                $sheet->getStyle('A1')->getAlignment()->setWrapText(true);
                $sheet->getRowDimension(1)->setRowHeight(20 * count($tipArr));
            }
        }

        // 表头处理
        $titleStart = 0;
        $validationFields = [];

        // 遍历属性配置，设置表头样式和数据验证
        foreach ($this->property as $item) {
            // 设置表头单元格位置
            $headerColumn = $this->getColumnIndex($titleStart) . $headerRow;

            // 设置表头值
            $sheet->setCellValue($headerColumn, $item['value']);

            // 设置表头字体加粗
            $style = $sheet->getStyle($headerColumn)->getFont()->setBold(true);

            // 设置列宽
            $columnDimension = $sheet->getColumnDimension($headerColumn[0]);
            empty($item['width']) ? $columnDimension->setAutoSize(true) : $columnDimension->setWidth((float) $item['width']);

            // 设置对齐方式
            empty($item['align']) || $sheet->getStyle($headerColumn)->getAlignment()->setHorizontal($item['align']);

            // 设置字体颜色
            empty($item['headColor']) || $style->setColor(new Color(str_replace('#', '', (string) $item['headColor'])));

            // 设置背景颜色
            if (! empty($item['headBgColor'])) {
                $sheet->getStyle($headerColumn)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB(str_replace('#', '', (string) $item['headBgColor']));
            }

            // 收集需要添加下拉选择的字段
            if (! empty($item['dictNameArr'])) {
                // dictNameArr 已经是值数组格式
                $validationFields[$titleStart] = array_values($item['dictNameArr']);
            } elseif (! empty($item['dictData'])) {
                // dictData 可能是键值对格式 ['1'=>'男', '2'=>'女']，需要转换为值数组
                if (is_array($item['dictData'])) {
                    // 检查是否是关联数组（键值对）
                    $keys = array_keys($item['dictData']);
                    $isAssoc = array_keys($keys) !== $keys;
                    if ($isAssoc) {
                        // 键值对格式，提取值
                        $validationFields[$titleStart] = array_values($item['dictData']);
                    } else {
                        // 已经是值数组格式
                        $validationFields[$titleStart] = $item['dictData'];
                    }
                }
            }

            ++$titleStart;
        }

        // 生成Excel数据
        $generate = $this->yieldExcelData($data);

        // 表体处理
        $maxRow = $dataStartRow;
        try {
            $row = $dataStartRow;

            // 遍历生成的数据
            while ($generate->valid()) {
                $column = 0;
                $items = $generate->current();

                // 遍历每个字段
                foreach ($items as $name => $value) {
                    // 计算单元格位置
                    $columnRow = $this->getColumnIndex($column) . $row;
                    $annotation = '';

                    // 查找对应的属性配置
                    foreach ($this->property as $item) {
                        if ($item['name'] == $name) {
                            $annotation = $item;
                            break;
                        }
                    }

                    // 根据属性配置设置单元格值
                    if (! empty($annotation['dictNameArr'])) {
                        $sheet->setCellValue($columnRow, $annotation['dictNameArr'][$value] ?? '');
                    } elseif (! empty($annotation['dictName'])) {
                        $sheet->setCellValue($columnRow, $annotation['dictName'][$value] ?? '');
                    } elseif (! empty($annotation['path'])) {
                        $sheet->setCellValue($columnRow, data_get($items, $annotation['path']));
                    } elseif (! empty($annotation['dictData'])) {
                        $sheet->setCellValue($columnRow, $annotation['dictData'][$value] ?? '');
                    } elseif (! empty($this->dictData[$name])) {
                        $sheet->setCellValue($columnRow, $this->dictData[$name][$value] ?? '');
                    } else {
                        $sheet->setCellValue($columnRow, $value . "\t");
                    }

                    // 设置字体颜色
                    if (! empty($annotation['color'])) {
                        $sheet->getStyle($columnRow)->getFont()
                            ->setColor(new Color(str_replace('#', '', (string) $annotation['color'])));
                    }

                    // 设置背景颜色
                    if (! empty($annotation['bgColor'])) {
                        $sheet->getStyle($columnRow)->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setARGB(str_replace('#', '', (string) $annotation['bgColor']));
                    }

                    // 设置数据行对齐方式
                    if (! empty($annotation['align'])) {
                        $sheet->getStyle($columnRow)->getAlignment()->setHorizontal($annotation['align']);
                    }


                    ++$column;
                }

                $generate->next();
                ++$row;
                $maxRow = $row;
            }
        } catch (RuntimeException $e) {
            // 捕获运行时异常
        }

        // 添加下拉选择数据验证
        foreach ($validationFields as $columnIndex => $options) {
            if (empty($options)) {
                continue;
            }

            $column = $this->getColumnIndex($columnIndex);
            // 为数据行添加下拉选择，至少到第100行
            $endRow = max($maxRow, 100);
            $validationRange = $column . $dataStartRow . ':' . $column . $endRow;

            // 创建数据验证对象
            $validation = new DataValidation();
            // 仅允许从列表中选择，避免非法输入
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setErrorTitle('输入错误');
            $validation->setError('请从下拉列表中选择一个值');
            $validation->setPromptTitle('请选择');
            $validation->setPrompt('请从下拉列表中选择一个值');

            // PhpSpreadsheet 中 TYPE_LIST 的公式格式为 "选项1,选项2,选项3"
            // 选项值中的引号需要转义为双引号
            $formulaOptions = array_map(function ($option) {
                return str_replace('"', '""', (string) $option);
            }, $options);

            // 公式格式：整个列表用引号包裹，选项之间用逗号分隔
            $validation->setFormula1('"' . implode(',', $formulaOptions) . '"');

            // 应用到整个列
            $sheet->setDataValidation($validationRange, $validation);
        }

        // 创建写入器并输出文件
        $writer = IOFactory::createWriter($spread, 'Xlsx');
        ob_start();
        $writer->save('php://output');
        $res = $this->downloadExcel($filename, ob_get_contents());
        ob_end_clean();
        $spread->disconnectWorksheets();

        return $res;
    }

    /**
     * 将二维数组数据按 property 定义顺序生成.
     *
     * @param array $data 引用传入的导出数据
     */
    protected function yieldExcelData(array &$data): Generator
    {
        // 遍历数据数组
        foreach ($data as $dat) {
            $yield = [];

            // 按属性配置顺序生成数据
            foreach ($this->property as $item) {
                $yield[$item['name']] = $dat[$item['name']] ?? '';
            }

            yield $yield;
        }
    }
}

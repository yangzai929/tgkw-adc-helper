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
use TgkwAdc\Constants\I18n\Common\CommonI18n;
use TgkwAdc\Exception\BusinessException;
use TgkwAdc\Helper\StrHelper;
use TgkwAdc\Helper\XlsWriterHelper;
use TgkwAdc\Office\Excel;
use TgkwAdc\Office\Interfaces\ExcelPropertyInterface;
use Vtiful\Kernel\Format;
use Vtiful\Kernel\Validation;

class XlsWriter extends Excel implements ExcelPropertyInterface
{
    public static function getSheetData(mixed $request): array
    {
        $file = $request->file('file');
        $tempFileName = 'import_' . time() . '.' . $file->getExtension();
        $tempFilePath = RUNTIME_BASE_PATH . '/' . $tempFileName;
        file_put_contents($tempFilePath, $file->getStream()->getContents());
        $xlsxObject = new \Vtiful\Kernel\Excel(['path' => RUNTIME_BASE_PATH . '/']);
        return $xlsxObject->openFile($tempFileName)->openSheet()->getSheetData();
    }

    /**
     * 导入数据.
     */
    public function import(Model $model, ?Closure $closure = null, int $orgId = 0): bool
    {
        $request = container()->get(RequestInterface::class);
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $tempFileName = 'import_' . time() . '_' . mt_rand(10000, 99999) . '.' . $file->getExtension();
            $tempFilePath = RUNTIME_BASE_PATH . '/' . $tempFileName;
            file_put_contents($tempFilePath, $file->getStream()->getContents());
            $xlsxObject = new \Vtiful\Kernel\Excel(['path' => RUNTIME_BASE_PATH . '/']);

            // 统一设置为字符串类型
            $setTypeArr = [];
            for ($i = 0; $i < count($this->property); ++$i) {
                $setTypeArr[] = \Vtiful\Kernel\Excel::TYPE_STRING;
            }
            $data = $xlsxObject->openFile($tempFileName)->openSheet()->setType($setTypeArr)->getSheetData();
            unset($data[0], $data[1]);

            $xlsWriterHelper = new XlsWriterHelper();

            $importData = [];
            foreach ($data as $item) {
                $tmp = [];
                $errorMsg = '';
                $emptyRow = true;
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
                if ($emptyRow) {
                    continue;
                }
                $tmp['result'] = $errorMsg;
                $importData[] = $tmp;
            }

            if ($closure instanceof Closure) {
                return $closure($model, $importData);
            }

            try {
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
     * 导出excel.
     */
    public function export(string $filename, array|Closure $closure, ?Closure $callbackData = null, bool $isDemo = false, int $orgId = 0, array $infos = []): \Psr\Http\Message\ResponseInterface
    {
        $filename .= '.xlsx';
        is_array($closure) ? $data = &$closure : $data = $closure();

        $aligns = [
            'left' => Format::FORMAT_ALIGN_LEFT,
            'center' => Format::FORMAT_ALIGN_CENTER,
            'right' => Format::FORMAT_ALIGN_RIGHT,
        ];

        $columnName = [];
        $columnField = [];
        $columnTip = [];
        $validationField = [];

        foreach ($this->property as $item) {
            $columnName[] = $item['value'];
            $columnField[] = $item['name'];

            if (! empty($item['tip'])) {
                $columnTip[] = [
                    'value' => $item['value'],
                    'tip' => $item['tip'],
                ];
            }
        }

        $tempFileName = 'export_' . time() . '.xlsx';
        $xlsxObject = new \Vtiful\Kernel\Excel(['path' => RUNTIME_BASE_PATH . '/']);
        $fileObject = $xlsxObject->fileName($tempFileName)->header($columnName);
        $columnFormat = new Format($fileObject->getHandle());
        $rowFormat = new Format($fileObject->getHandle());

        for ($i = 0; $i < count($columnField); ++$i) {
            $fileObject->setColumn(
                sprintf('%s1:%s1', $this->getColumnIndex($i), $this->getColumnIndex($i)),
                $this->property[$i]['width'] ?? mb_strlen($columnName[$i]) * 5,
                $columnFormat->align($this->property[$i]['align'] ? $aligns[$this->property[$i]['align']] : $aligns['left'])
                    ->background($this->property[$i]['bgColor'] ?? Format::COLOR_WHITE)
                    ->border(Format::BORDER_THIN)
                    ->fontColor($this->property[$i]['color'] ?? Format::COLOR_BLACK)
                    ->toResource()
            );
            // 判断校验字段
            if (! empty($this->property[$i]['dictNameArr'])) {
                $validationField[$i] = array_values($this->property[$i]['dictNameArr']);
            } elseif (! empty($this->property[$i]['dictData'])) {
                $validationField[$i] = array_values($this->property[$i]['dictData']);
            }
        }

        // 表头加样式
        if (empty($infos['is_export'])) {
            $fileObject->setRow(
                sprintf('A1:%s1', $this->getColumnIndex(count($columnField))),
                $this->property[0]['headHeight'] ?? 24,
                $rowFormat->bold()->toResource()
            );
        } else {
            $fileObject->setRow(
                sprintf('A1:%s1', $this->getColumnIndex(count($columnField))),
                $this->property[0]['headHeight'] ?? 24,
                $rowFormat->bold()
                    ->background($this->property[0]['headBgColor'] ?? 0x4AC1FF)
                    ->fontColor($this->property[0]['headColor'] ?? Format::COLOR_BLACK)
                    ->toResource()
            );
        }

        // 表内容加样式
        $dataLength = max(count($data), 50);
        $fileObject->setRow(
            sprintf('A2:A%s', $dataLength + 2),
            $this->property[0]['height'] ?? 24,
            (new Format($fileObject->getHandle()))->align(Format::FORMAT_ALIGN_VERTICAL_CENTER)->toResource()
        );

        if (empty($infos['is_export'])) {
            for ($i = 0; $i < count($columnField); ++$i) {
                $fileObject->insertText(
                    1,
                    $i,
                    $columnName[$i],
                    null,
                    (new Format($fileObject->getHandle()))
                        ->bold()
                        ->align(Format::FORMAT_ALIGN_CENTER, Format::FORMAT_ALIGN_VERTICAL_CENTER)
                        ->background($this->property[$i]['headBgColor'] ?? 0x4AC1FF)
                        ->fontColor($this->property[$i]['headColor'] ?? Format::COLOR_BLACK)
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

        if (empty($infos['is_export'])) {
            $tipArr = [
                CommonI18n::TIP->genI18nTxt(returnNowLang: true),
                '1. ' . CommonI18n::DONT_MODIFY_TABLE_STRUCTURE->genI18nTxt(returnNowLang: true),
                '2. ' . CommonI18n::RED_FIELDS_REQUIRED->genI18nTxt(returnNowLang: true),
            ];
            foreach ($columnTip as $item) {
                $tipArr[] = count($tipArr) . '. ' . $item['value'] . ': ' . $item['tip'];
            }
            foreach ($infos['tips'] as $infoTip) {
                $tipArr[] = count($tipArr) . '. ' . $infoTip['value'] . ': ' . $infoTip['tip'];
            }
            $fileObject->mergeCells(sprintf('A1:%s1', $this->getColumnIndex(count($columnField) - 1)), implode(PHP_EOL, $tipArr));
            $fileObject->setRow(
                'A1:A1',
                20 * count($tipArr),
                (new Format($fileObject->getHandle()))->align(Format::FORMAT_ALIGN_LEFT, Format::FORMAT_ALIGN_VERTICAL_TOP)->wrap()->toResource()
            );
        }

        $response = container()->get(ResponseInterface::class);

        $filePath = $fileObject->data($exportData);

        // 判断校验字段
        foreach ($validationField as $key => $item) {
            $validation = new Validation();
            $validation = $validation->validationType(Validation::TYPE_LIST)->valueList($item);
            $forRows = max(count($exportData), 22);
            $column = $this->getColumnIndex($key);
            for ($i = 3; $i < $forRows; ++$i) {
                $filePath = $filePath->validation($column . $i, $validation->toResource());
            }
        }

        $filePath = $filePath->output();

        $response->download($filePath, $filename);

        ob_start();
        if (copy($filePath, 'php://output') === false) {
            throw new BusinessException(0, CommonCode::EXPORT_FAILED->genI18nMsg(returnNowLang: true));
        }
        $res = $this->downloadExcel($filename, ob_get_contents());
        ob_end_clean();

        @unlink($filePath);

        return $res;
    }
}

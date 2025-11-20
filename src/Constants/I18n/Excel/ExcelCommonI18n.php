<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Constants\I18n\Excel;

use TgkwAdc\Annotation\EnumI18n;
use TgkwAdc\Annotation\EnumI18nGroup;
use TgkwAdc\Annotation\EnumI18nInterface;
use TgkwAdc\Trait\EnumI18nGet;

#[EnumI18nGroup(groupCode: 'ExcelCommon', info: 'excel导入公共类')]
enum ExcelCommonI18n: int implements EnumI18nInterface
{
    use EnumI18nGet;

    #[EnumI18n(txt: '导入结果', i18nTxt: ['en' => 'Import Result', 'zh_hk' => '導入結果'])]
    case IMPORT_RESULT = 1;

    #[EnumI18n(txt: '提示：', i18nTxt: ['en' => 'Tip:', 'zh_hk' => '提示：'])]
    case TIP = 2;

    #[EnumI18n(
        txt: '请不要修改表结构。',
        i18nTxt: ['en' => 'Please do not modify the table structure.', 'zh_hk' => '請不要修改表結構。']
    )]
    case DONT_MODIFY_TABLE_STRUCTURE = 3;

    #[EnumI18n(
        txt: '红色字段是必填项，黑色字段是选填项。',
        i18nTxt: ['en' => 'Red fields are required, black fields are optional.', 'zh_hk' => '紅色欄位是必填項，黑色欄位是選填項。']
    )]
    case RED_FIELDS_REQUIRED = 4;

    #[EnumI18n(
        txt: '错误行数',
        i18nTxt: ['en' => 'Error line number', 'zh_cn' => '错误行数', 'zh_hk' => '錯誤行數']
    )]
    case FAILED_LINE = 5;

    #[EnumI18n(txt: '错误原因', i18nTxt: ['en' => 'Error reason', 'zh_cn' => '错误原因', 'zh_hk' => '錯誤原因'])]
    case FAILED_ERROR = 6;

    #[EnumI18n(txt: '导入成功', i18nTxt: ['en' => 'Import successful', 'zh_cn' => '导入成功', 'zh_hk' => '導入成功'])]
    case IMPORT_SUCCESS = 7;

    #[EnumI18n(txt: '导入失败,请检查', i18nTxt: ['en' => 'Import failed, please check', 'zh_cn' => '导入失败,请检查', 'zh_hk' => '導入失敗,請檢查'])]
    case IMPORT_FAILED = 8;
}

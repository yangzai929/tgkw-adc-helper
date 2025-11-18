<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Constants\I18n\Common;

use TgkwAdc\Annotation\EnumI18n;
use TgkwAdc\Annotation\EnumI18nGroup;
use TgkwAdc\Annotation\EnumI18nInterface;
use TgkwAdc\Trait\EnumI18nGet;

#[EnumI18nGroup(groupCode: 'common', info: '公共类')]
enum CommonI18n: int implements EnumI18nInterface
{
    use EnumI18nGet;

    #[EnumI18n(txt: '导入结果', i18nTxt: ['en' => 'Import Result', 'zh_tw' => '導入結果', 'zh_hk' => '導入結果', 'ja' => '導入結果'])]
    case IMPORT_RESULT = 1;

    #[EnumI18n(txt: '提示：', i18nTxt: ['en' => 'Tip:', 'zh_tw' => '提示：', 'zh_hk' => '提示：', 'ja' => '提示：'])]
    case TIP = 2;

    #[EnumI18n(txt: '请不要修改表结构。', i18nTxt: ['en' => 'Please do not modify the table structure.', 'zh_tw' => '請不要修改表結構。', 'zh_hk' => '請不要修改表結構。', 'ja' => '請不要修改表結構。'])]
    case DONT_MODIFY_TABLE_STRUCTURE = 3;

    #[EnumI18n(txt: '红色字段是必填项，黑色字段是选填项。', i18nTxt: ['en' => 'Red fields are required, black fields are optional.', 'zh_tw' => '紅色欄位是必填項，黑色欄位是選填項。', 'zh_hk' => '紅色欄位是必填項，黑色欄位是選填項。', 'ja' => '紅色欄位是必填項，黑色欄位是選填項。'])]
    case RED_FIELDS_REQUIRED = 4;
}

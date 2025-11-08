<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Constants\Code;

use TgkwAdc\Annotation\EnumCode;
use TgkwAdc\Annotation\EnumCodeInterface;
use TgkwAdc\Annotation\EnumCodePrefix;
use TgkwAdc\Trait\EnumCodeGet;

#[EnumCodePrefix(prefixCode: 4000, info: 'token认证错误')]
enum TokenCode: int implements EnumCodeInterface
{
    use EnumCodeGet;

    #[EnumCode(
        msg: '令牌无效，请登录',
        i18nMsg: [
            'en' => 'Invalid token, please log in',
            'zh_hk' => '令牌無效，請登入',
        ]
    )]
    case INVALID_TOKEN = 1;

    #[EnumCode(
        msg: '令牌已过期，请重新获取令牌',
        i18nMsg: [
            'en' => 'Token has expired, please log in',
            'zh_hk' => '令牌已過期，請重新获取令牌',
        ]
    )]
    case EXPIRED_TOKEN = 2;
}

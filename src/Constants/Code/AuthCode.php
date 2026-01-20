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
enum AuthCode: int implements EnumCodeInterface
{
    use EnumCodeGet;

    #[EnumCode(
        msg: '请登录！',
        i18nMsg: [
            'en' => 'Please log in!',
            'zh_hk' => '請登入',
        ]
    )]
    case NEED_LOGIN = 1;

    #[EnumCode(
        msg: '令牌无效，请登录',
        i18nMsg: [
            'en' => 'Invalid token, please log in',
            'zh_hk' => '令牌無效，請登入',
        ]
    )]
    case INVALID_TOKEN = 2;

    #[EnumCode(
        msg: '令牌已过期，请重新获取令牌',
        i18nMsg: [
            'en' => 'Token has expired, please log in',
            'zh_hk' => '令牌已過期，請重新获取令牌',
        ]
    )]
    case EXPIRED_TOKEN = 3;

    #[EnumCode(
        msg: '参数错误，tenant_id不能为空',
        i18nMsg: [
            'en' => 'Parameter error: tenant_id cannot be empty',
            'zh_hk' => '參數錯誤，tenant_id不能為空',
        ]
    )]
    case EMPTY_TENANT_ID = 4;

    #[EnumCode(
        msg: 'tenant_id 错误，当前用户不属于当前租户',
        i18nMsg: [
            'en' => 'Invalid tenant_id: The current user does not belong to the current tenant',
            'zh_hk' => 'tenant_id 錯誤，當前用戶不屬於當前租戶',
        ]
    )]
    case ERROR_TENANT_ID = 5;

    #[EnumCode(
        msg: '无权访问',
        i18nMsg: [
            'en' => 'No authority to access',
            'zh_hk' => '無權訪問',
        ]
    )]
    case AUTH_ERROR = 6;

    #[EnumCode(
        msg: '无权访问（{action}）',
        i18nMsg: [
            'en' => 'No authority to access ({action})',
            'zh_hk' => '無權訪問（{action}）',
        ]
    )
    ]
    case AUTH_ERROR_ACTION = 7;

    #[EnumCode(
        msg: '权限不足，无法操作',
        i18nMsg: [
            'en' => 'Permission denied',
            'zh_hk' => '權限不足，無法操作',
        ]
    )]
    case PERMISSION_DENIED = 8;

    #[EnumCode(
        msg: '请先创建或加入租户',
        i18nMsg: [
            'en' => 'Please create or join a tenant first',
            'zh_hk' => '請先建立或加入租戶',
        ]
    )]
    case NEED_JOIN_TENANT = 9;

    #[EnumCode(
        msg: '请先选择租户',
        i18nMsg: [
            'en' => 'Please select a tenant first',
            'zh_hk' => '請先选择租戶',
        ]
    )]
    case NEED_SELECT_TENANT = 10;
}

<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Constants;

class GlobalConstants
{
    public const ORG_TOKEN_TYPE = 'ORG'; //客户端令牌类型标识

    public const ORG_TOKEN_KEY = 'Org-Token';//客户端令牌标识

    public const SYS_TOKEN_TYPE = 'SYS'; //系统总后台令牌类型标识

    public const SYS_TOKEN_KEY = 'System-Token'; //系统总后台令牌标识

    public const ORG_USER_CONTEXT = 'nowUser'; //协程上下文中客户端当前用户

    public const SYS_ADMIN_CONTEXT = 'nowSystemAdmin'; //协程上下文中系统总后台当前管理员
}

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
    public const ORG_TOKEN_TYPE = 'ORG'; // 客户端令牌类型标识

    public const ORG_TOKEN_KEY = 'Org-Token'; // 客户端令牌标识

    public const ORG_TOKEN_REDIS_KEY_PREFIX = 'user_token:token:'; // 客户端令牌缓存标识

    public const ORG_TOKEN_REDIS_SET_KEY_PREFIX = 'user_token:token_key:'; //  token 集合 标识

    public const ORG_REFRESH_TOKEN_REDIS_KEY = 'user_refresh_token:'; // 客户端刷新令牌缓存标识

    public const SYS_TOKEN_TYPE = 'SYS'; // 系统总后台令牌类型标识

    public const SYS_TOKEN_KEY = 'System-Token'; // 系统总后台令牌标识

    public const SYS_TOKEN_REDIS_KEY_PREFIX = 'admin_token:'; // 系统总后台令牌缓存标识

    public const SYS_TOKEN_REDIS_SET_KEY_PREFIX = 'admin_token:'; // 系统总后台令牌缓存标识

    public const ORG_USER_CONTEXT = 'nowUser'; // 协程上下文中客户端当前用户

    public const SYS_ADMIN_CONTEXT = 'nowSystemAdmin'; // 协程上下文中系统总后台当前管理员

    public const IS_CURRENT_TENANT_MAIN_ADMIN = 'is_current_tenant_main_admin'; // 协程上下文中标识当前用户是否是当前租户的主管理员

    public const CURRENT_TENANT_ID = 'current_tenant_id'; // 协程上下文中标识当前用户的当前租户ID
}

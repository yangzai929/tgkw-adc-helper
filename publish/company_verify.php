<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

return [
    // 默认使用的企业核验三方（对应 providers 下的 key）
    'default' => env('COMPANY_VERIFY_DRIVER', 'tianyancha'),

    // 各三方凭证配置
    'providers' => [
        // 天眼查开放平台
        'tianyancha' => [
            'token' => env('TIANYANCHA_TOKEN', ''),
        ],

        // 数脉数据
        'shumai' => [
            'app_code' => env('SHUMAI_APP_CODE', ''),
        ],
    ],
];

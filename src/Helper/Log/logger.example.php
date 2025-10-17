<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */
use Monolog\Formatter;
use Monolog\Handler;
use Monolog\Level;
use TgkwAdc\Helper\Log\AppendRequestIdProcessor;

// 获取环境变量，用于区分不同环境的日志文件名
$appEnv = env('APP_ENV', 'dev');
$appName = env('APP_NAME', 'hyperf');

return [
    // 默认日志配置
    'default' => [
        'handler' => [
            'class' => Monolog\Handler\StreamHandler::class,
            'constructor' => [
                'stream' => BASE_PATH . '/runtime/logs/hyperf.log',
                'level' => Level::Debug,
            ],
        ],
        'formatter' => [
            'class' => Monolog\Formatter\LineFormatter::class,
            'constructor' => [
                'format' => null,
                'dateFormat' => 'Y-m-d H:i:s',
                'allowInlineLineBreaks' => true,
            ],
        ],
    ],

    // 单文件日志处理器 - 支持日志轮转
    'single' => [
        'handler' => [
            'class' => Handler\RotatingFileHandler::class,
            'constructor' => [
                // 方式1: 使用环境变量自定义文件名
                'filename' => BASE_PATH . "/runtime/logs/{$appName}-{$appEnv}.log",
                // 方式2: 直接指定文件名
                // 'filename' => BASE_PATH . '/runtime/logs/my-app.log',
                // 方式3: 使用日期作为文件名
                // 'filename' => BASE_PATH . '/runtime/logs/app-' . date('Y-m-d') . '.log',
                'level' => Level::Info,
                'maxFiles' => 30, // 保留30天的日志
            ],
        ],
        'formatter' => [
            'class' => Formatter\LineFormatter::class,
            'constructor' => [
                'format' => null,
                'dateFormat' => null,
                'allowInlineLineBreaks' => true,
            ],
        ],
    ],

    // 按日期轮转的日志处理器 - 自定义文件名示例
    'daily' => [
        'handler' => [
            'class' => Handler\RotatingFileHandler::class,
            'constructor' => [
                // 方式1: 使用环境变量
                'filename' => BASE_PATH . "/runtime/logs/{$appName}-debug-{$appEnv}.log",
                // 方式2: 直接指定文件名
                // 'filename' => BASE_PATH . '/runtime/logs/my-app-debug.log',
                // 方式3: 按模块分类
                // 'filename' => BASE_PATH . '/runtime/logs/api-debug.log',
                'level' => Level::Debug,
                'maxFiles' => 30, // 保留30天的日志
            ],
        ],
        'formatter' => [
            'class' => Formatter\JsonFormatter::class,
            'constructor' => [
                'batchMode' => Formatter\JsonFormatter::BATCH_MODE_JSON,
                'appendNewline' => true,
            ],
        ],
    ],

    // 业务日志配置 - 支持日志轮转
    'business' => [
        'handler' => [
            'class' => Handler\RotatingFileHandler::class,
            'constructor' => [
                // 方式1: 按模块分类
                'filename' => BASE_PATH . "/runtime/logs/{$appName}-business-{$appEnv}.log",
                // 方式2: 按功能分类
                // 'filename' => BASE_PATH . '/runtime/logs/order-business.log',
                // 方式3: 按日期分类
                // 'filename' => BASE_PATH . '/runtime/logs/business-' . date('Y-m-d') . '.log',
                'level' => Level::Info,
                'maxFiles' => 30, // 保留30天的日志
            ],
        ],
        'formatter' => [
            'class' => Formatter\LineFormatter::class,
            'constructor' => [
                'format' => "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
                'dateFormat' => 'Y-m-d H:i:s',
                'allowInlineLineBreaks' => true,
            ],
        ],
    ],

    // 访问日志配置 - 支持日志轮转
    'access' => [
        'handler' => [
            'class' => Handler\RotatingFileHandler::class,
            'constructor' => [
                // 方式1: 按环境分类
                'filename' => BASE_PATH . "/runtime/logs/{$appName}-access-{$appEnv}.log",
                // 方式2: 按服务分类
                // 'filename' => BASE_PATH . '/runtime/logs/api-access.log',
                // 方式3: 按日期分类
                // 'filename' => BASE_PATH . '/runtime/logs/access-' . date('Y-m-d') . '.log',
                'level' => Level::Info,
                'maxFiles' => 30, // 保留30天的日志
            ],
        ],
        'formatter' => [
            'class' => Formatter\JsonFormatter::class,
            'constructor' => [
                'batchMode' => Formatter\JsonFormatter::BATCH_MODE_JSON,
                'appendNewline' => true,
            ],
        ],
    ],

    // 系统日志配置 - 支持日志轮转
    'system' => [
        'handler' => [
            'class' => Handler\RotatingFileHandler::class,
            'constructor' => [
                // 方式1: 按环境分类
                'filename' => BASE_PATH . "/runtime/logs/{$appName}-system-{$appEnv}.log",
                // 方式2: 按服务分类
                // 'filename' => BASE_PATH . '/runtime/logs/monitor-system.log',
                'level' => Level::Warning,
                'maxFiles' => 30, // 保留30天的日志
            ],
        ],
        'formatter' => [
            'class' => Formatter\LineFormatter::class,
            'constructor' => [
                'format' => "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
                'dateFormat' => 'Y-m-d H:i:s',
                'allowInlineLineBreaks' => true,
            ],
        ],
    ],

    // 异常日志配置 - 支持日志轮转
    'exception' => [
        'handler' => [
            'class' => Handler\RotatingFileHandler::class,
            'constructor' => [
                // 方式1: 按环境分类
                'filename' => BASE_PATH . "/runtime/logs/{$appName}-exception-{$appEnv}.log",
                // 方式2: 按服务分类
                // 'filename' => BASE_PATH . '/runtime/logs/error-exception.log',
                'level' => Level::Error,
                'maxFiles' => 30, // 保留30天的日志
            ],
        ],
        'formatter' => [
            'class' => Formatter\LineFormatter::class,
            'constructor' => [
                'format' => "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
                'dateFormat' => 'Y-m-d H:i:s',
                'allowInlineLineBreaks' => true,
                'includeStacktraces' => true,
            ],
        ],
    ],

    // 自定义日志配置示例 - 按模块分类，支持日志轮转
    'api' => [
        'handler' => [
            'class' => Handler\RotatingFileHandler::class,
            'constructor' => [
                'filename' => BASE_PATH . "/runtime/logs/{$appName}-api-{$appEnv}.log",
                'level' => Level::Info,
                'maxFiles' => 30, // 保留30天的日志
            ],
        ],
        'formatter' => [
            'class' => Formatter\LineFormatter::class,
            'constructor' => [
                'format' => "[%datetime%] API.%level_name%: %message% %context% %extra%\n",
                'dateFormat' => 'Y-m-d H:i:s',
                'allowInlineLineBreaks' => true,
            ],
        ],
    ],

    // 自定义日志配置示例 - 按功能分类
    'payment' => [
        'handler' => [
            'class' => Handler\RotatingFileHandler::class,
            'constructor' => [
                'filename' => BASE_PATH . "/runtime/logs/{$appName}-payment-{$appEnv}.log",
                'level' => Level::Info,
                'maxFiles' => 90, // 支付日志保留90天
            ],
        ],
        'formatter' => [
            'class' => Formatter\JsonFormatter::class,
            'constructor' => [
                'batchMode' => Formatter\JsonFormatter::BATCH_MODE_JSON,
                'appendNewline' => true,
            ],
        ],
    ],
];

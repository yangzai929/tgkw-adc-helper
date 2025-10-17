<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use TgkwAdc\Helper\Log\AppendRequestIdProcessor;
use TgkwAdc\Helper\Log\CustomJsonFormatter;

$appEnv = env('APP_ENV', 'dev');
$appName = env('APP_NAME', 'hyperf');
$logPath = env('LOG_PATH', BASE_PATH . '/runtime/logs');

$defaultLineFormatter = [
    'class' => LineFormatter::class,
    'constructor' => [
        'format' => "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
        'dateFormat' => 'Y-m-d H:i:s',
        'allowInlineLineBreaks' => true,
    ],
];

$defaultJsonFormatter = [
    'class' => CustomJsonFormatter::class,
    'constructor' => [
        'batchMode' => JsonFormatter::BATCH_MODE_JSON,
        'appendNewline' => true,
    ],
];

return [
    // 默认日志组
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

    // 单文件日志
    'single' => [
        'handlers' => [
            [
                'class' => RotatingFileHandler::class,
                'constructor' => [
                    'filename' => "{$logPath}/{$appName}-{$appEnv}.log",
                    'level' => Level::Info,
                    'maxFiles' => 30,
                ],
                'formatter' => $defaultLineFormatter,
            ],
        ],
        'processors' => [
            ['class' => AppendRequestIdProcessor::class],
        ],
    ],

    // 每日调试日志
    'daily' => [
        'handlers' => [
            [
                'class' => RotatingFileHandler::class,
                'constructor' => [
                    'filename' => "{$logPath}/{$appName}-debug-{$appEnv}.log",
                    'level' => Level::Debug,
                    'maxFiles' => 30,
                ],
                'formatter' => $defaultJsonFormatter,
            ],
        ],
    ],

    // 业务日志
    'business' => [
        'handlers' => [
            [
                'class' => RotatingFileHandler::class,
                'constructor' => [
                    'filename' => "{$logPath}/{$appName}-business-{$appEnv}.log",
                    'level' => Level::Info,
                    'maxFiles' => 90,
                ],
                'formatter' => $defaultLineFormatter,
            ],
        ],
    ],

    // 访问日志（带 request_id）
    'access' => [
        'handlers' => [
            [
                'class' => RotatingFileHandler::class,
                'constructor' => [
                    'filename' => "{$logPath}/{$appName}-access-{$appEnv}.log",
                    'level' => Level::Info,
                    'maxFiles' => 30,
                ],
                'formatter' => $defaultJsonFormatter,
            ],
        ],
        'processors' => [
            ['class' => AppendRequestIdProcessor::class],
        ],
    ],

    // 系统日志
    'system' => [
        'handlers' => [
            [
                'class' => RotatingFileHandler::class,
                'constructor' => [
                    'filename' => "{$logPath}/{$appName}-system-{$appEnv}.log",
                    'level' => Level::Warning,
                    'maxFiles' => 30,
                ],
                'formatter' => $defaultLineFormatter,
            ],
        ],
    ],

    // 异常日志
    'exception' => [
        'handlers' => [
            [
                'class' => RotatingFileHandler::class,
                'constructor' => [
                    'filename' => "{$logPath}/{$appName}-exception-{$appEnv}.log",
                    'level' => Level::Error,
                    'maxFiles' => 30,
                ],
                'formatter' => [
                    'class' => LineFormatter::class,
                    'constructor' => [
                        'format' => "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
                        'dateFormat' => 'Y-m-d H:i:s',
                        'allowInlineLineBreaks' => true,
                        'includeStacktraces' => true,
                    ],
                ],
            ],
        ],
    ],

    // 调试日志
    'debug' => [
        'handlers' => [
            [
                'class' => RotatingFileHandler::class,
                'constructor' => [
                    'filename' => "{$logPath}/{$appName}-debug-{$appEnv}.log",
                    'level' => Level::Debug,
                    'maxFiles' => 7,
                ],
                'formatter' => $defaultLineFormatter,
            ],
        ],
    ],

    // 支付日志
    'payment' => [
        'handlers' => [
            [
                'class' => RotatingFileHandler::class,
                'constructor' => [
                    'filename' => "{$logPath}/{$appName}-payment-{$appEnv}.log",
                    'level' => Level::Info,
                    'maxFiles' => 90,
                ],
                'formatter' => $defaultJsonFormatter,
            ],
        ],
    ],
];

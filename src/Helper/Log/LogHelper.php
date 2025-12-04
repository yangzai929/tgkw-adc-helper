<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Helper\Log;

use Hyperf\Context\ApplicationContext;
use Hyperf\Logger\LoggerFactory;
use Monolog\Handler\BufferHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Throwable;

class LogHelper
{
    /**
     * 缓存 Logger 实例.
     */
    private static array $dynamicLoggers = [];

    /**
     * 获取容器中的 Logger.
     */
    public static function get(string $name = 'app', string $group = 'default'): LoggerInterface
    {
        return ApplicationContext::getContainer()
            ->get(LoggerFactory::class)
            ->get($name, $group);
    }

    /**
     * 获取动态日志记录器.
     */
    public static function getDynamic(
        string $name,
        string $filename,
        string $level = 'info',
        int $maxFiles = 30,
        bool $async = false,
    ): LoggerInterface {
        $cacheKey = "{$name}_{$filename}_{$level}_{$maxFiles}_{$async}";

        if (isset(self::$dynamicLoggers[$cacheKey])) {
            return self::$dynamicLoggers[$cacheKey];
        }

        // 日志目录（可配置）
        $logDir = env('LOG_PATH', BASE_PATH . '/runtime/logs');
        if (! is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        $logPath = "{$logDir}/{$filename}.log";

        $logger = new Logger($name);
        $handler = new RotatingFileHandler($logPath, $maxFiles, Logger::toMonologLevel($level));

        // 异步缓冲写入
        if ($async) {
            $handler = new BufferHandler($handler, 50, Logger::toMonologLevel($level), true, true);
        }

        $logger->pushHandler($handler);

        self::$dynamicLoggers[$cacheKey] = $logger;

        return $logger;
    }

    // 以下是不同级别的快捷方法
    public static function info(
        string $message,
        array $context = [],
        ?string $filename = null,
        string $name = 'log',
        string $group = 'default',
        bool $async = false
    ): void {
        self::log(
            'info',
            $message,
            $context,
            $name,
            $group,
            $filename,
            $async
        );
    }

    public static function notice(
        string $message,
        array $context = [],
        ?string $filename = null,
        string $name = 'log',
        string $group = 'default',
        bool $async = false
    ): void {
        self::log(
            'notice',
            $message,
            $context,
            $name,
            $group,
            $filename,
            $async
        );
    }

    public static function warning(
        string $message,
        array $context = [],
        ?string $filename = null,
        string $name = 'log',
        string $group = 'default',
        bool $async = false
    ): void {
        self::log(
            'warning',
            $message,
            $context,
            $name,
            $group,
            $filename,
            $async
        );
    }

    public static function error(
        string $message,
        array $context = [],
        ?string $filename = null,
        string $name = 'log',
        string $group = 'default',
        bool $async = false
    ): void {
        self::log(
            'error',
            $message,
            $context,
            $name,
            $group,
            $filename,
            $async
        );
    }

    public static function critical(
        string $message,
        array $context = [],
        ?string $filename = null,
        string $name = 'log',
        string $group = 'default',
        bool $async = false
    ): void {
        self::log(
            'critical',
            $message,
            $context,
            $name,
            $group,
            $filename,
            $async
        );
    }

    public static function alert(
        string $message,
        array $context = [],
        ?string $filename = null,
        string $name = 'log',
        string $group = 'default',
        bool $async = false
    ): void {
        self::log(
            'alert',
            $message,
            $context,
            $name,
            $group,
            $filename,
            $async
        );
    }

    public static function emergency(
        string $message,
        array $context = [],
        ?string $filename = null,
        string $name = 'log',
        string $group = 'default',
        bool $async = false
    ): void {
        self::log(
            'emergency',
            $message,
            $context,
            $name,
            $group,
            $filename,
            $async
        );
    }

    // 调试日志
    public static function debug(
        string $message,
        array $context = [],
        string $name = 'app_log',
        string $group = 'debug', // 默认走 debug 组
        ?string $filename = null,
        bool $async = false,
    ): void {
        self::log('debug', $message, $context, $name, $group, $filename, $async);
    }

    /**
     * 业务日志.
     */
    public static function business(string $message, array $context = [], string $name = 'business_log', ?string $filename = null, bool $async = false): void
    {
        self::log('info', $message, $context, $name, 'business', $filename, $async);
    }

    /**
     * 访问日志.
     */
    public static function access(string $message, array $context = [], string $name = 'access_log', ?string $filename = null, bool $async = false): void
    {
        self::log('info', $message, $context, $name, 'access', $filename, $async);
    }

    /**
     * 系统日志.
     */
    public static function system(string $message, array $context = [], string $name = 'system_log', ?string $filename = null, bool $async = false): void
    {
        self::log('warning', $message, $context, $name, 'system', $filename, $async);
    }

    /**
     * 异常日志.
     */
    public static function exception(Throwable $exception, string $message = '', array $context = [], string $name = 'exception', ?string $filename = null, bool $async = false): void
    {
        $logMessage = $message ?: $exception->getMessage();
        $context['exception'] = [
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ];

        self::log('error', $logMessage, $context, $name, 'exception', $filename, $async);
    }

    /**
     * 从配置中获取日志级别.
     */
    private static function getConfigLevel(string $group): int|string
    {
        $configLevel = config("logger.{$group}.handler.constructor.level") ?? 'info';

        // 如果是 Monolog 3.x 的 Level 对象
        if ($configLevel instanceof Level) {
            return strtolower($configLevel->name); // 返回 'info'、'debug' 等
        }

        // 如果是 Monolog 2.x 的整数常量
        if (is_int($configLevel)) {
            return strtolower(Logger::getLevelName($configLevel));
        }

        // 如果本来就是字符串
        if (is_string($configLevel)) {
            return strtolower($configLevel);
        }

        return $configLevel;
    }

    /**
     * 通用日志方法.
     */
    private static function log(
        string $method,
        string $message,
        array $context = [],
        string $name = 'log',
        string $group = 'default',
        ?string $filename = null,
        bool $async = false,
    ): void {
        $level = self::getConfigLevel($group);

        if ($filename) {
            self::getDynamic($name, $filename, $level, 30, $async)->{$method}($message, $context);
        } else {
            self::get($name, $group)->{$method}($message, $context);
        }
    }
}

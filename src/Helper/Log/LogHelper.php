<?php

declare(strict_types=1);

namespace TgkwAdc\Helper\Log;

use Hyperf\Context\ApplicationContext;
use Hyperf\Logger\LoggerFactory;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class LogHelper
{
    /**
     * 动态日志记录器缓存
     */
    private static array $dynamicLoggers = [];

    /**
     * 获取日志记录器
     *
     * @param string $name 日志通道名称
     * @param string $group 日志组名称，默认为 'default'
     * @return LoggerInterface
     */
    public static function get(string $name = 'app', string $group = 'default'): LoggerInterface
    {
        return ApplicationContext::getContainer()
            ->get(LoggerFactory::class)
            ->get($name, $group);
    }

    /**
     * 获取动态日志记录器（支持自定义文件名）
     *
     * @param string $name 日志通道名称
     * @param string $filename 自定义日志文件名（不包含路径和扩展名）
     * @param string $level 日志级别
     * @return LoggerInterface
     */
    public static function getDynamic(string $name, string $filename, string $level = 'info'): LoggerInterface
    {
        $cacheKey = "{$name}_{$filename}_{$level}";
        
        if (isset(self::$dynamicLoggers[$cacheKey])) {
            return self::$dynamicLoggers[$cacheKey];
        }

        $logger = new Logger($name);
        $logPath = BASE_PATH . "/runtime/logs/{$filename}.log";
        
        $handler = new StreamHandler($logPath, Logger::toMonologLevel($level));
        $logger->pushHandler($handler);
        
        self::$dynamicLoggers[$cacheKey] = $logger;
        
        return $logger;
    }

    /**
     * 记录调试级别日志
     *
     * @param string $message 日志消息
     * @param array $context 上下文信息
     * @param string $name 日志通道名称
     * @param string $group 日志组名称
     * @param string|null $filename 自定义日志文件名（不包含路径和扩展名）
     */
    public static function debug(string $message, array $context = [], string $name = 'app', string $group = 'default', ?string $filename = null): void
    {
        if ($filename) {
            self::getDynamic($name, $filename, 'debug')->debug($message, $context);
        } else {
            self::get($name, $group)->debug($message, $context);
        }
    }

    /**
     * 记录信息级别日志
     *
     * @param string $message 日志消息
     * @param array $context 上下文信息
     * @param string $name 日志通道名称
     * @param string $group 日志组名称
     * @param string|null $filename 自定义日志文件名（不包含路径和扩展名）
     */
    public static function info(string $message, array $context = [], string $name = 'app', string $group = 'default', ?string $filename = null): void
    {
        if ($filename) {
            self::getDynamic($name, $filename, 'info')->info($message, $context);
        } else {
            self::get($name, $group)->info($message, $context);
        }
    }

    /**
     * 记录通知级别日志
     *
     * @param string $message 日志消息
     * @param array $context 上下文信息
     * @param string $name 日志通道名称
     * @param string $group 日志组名称
     * @param string|null $filename 自定义日志文件名（不包含路径和扩展名）
     */
    public static function notice(string $message, array $context = [], string $name = 'app', string $group = 'default', ?string $filename = null): void
    {
        if ($filename) {
            self::getDynamic($name, $filename, 'notice')->notice($message, $context);
        } else {
            self::get($name, $group)->notice($message, $context);
        }
    }

    /**
     * 记录警告级别日志
     *
     * @param string $message 日志消息
     * @param array $context 上下文信息
     * @param string $name 日志通道名称
     * @param string $group 日志组名称
     * @param string|null $filename 自定义日志文件名（不包含路径和扩展名）
     */
    public static function warning(string $message, array $context = [], string $name = 'app', string $group = 'default', ?string $filename = null): void
    {
        if ($filename) {
            self::getDynamic($name, $filename, 'warning')->warning($message, $context);
        } else {
            self::get($name, $group)->warning($message, $context);
        }
    }

    /**
     * 记录错误级别日志
     *
     * @param string $message 日志消息
     * @param array $context 上下文信息
     * @param string $name 日志通道名称
     * @param string $group 日志组名称
     * @param string|null $filename 自定义日志文件名（不包含路径和扩展名）
     */
    public static function error(string $message, array $context = [], string $name = 'app', string $group = 'default', ?string $filename = null): void
    {
        if ($filename) {
            self::getDynamic($name, $filename, 'error')->error($message, $context);
        } else {
            self::get($name, $group)->error($message, $context);
        }
    }

    /**
     * 记录严重错误级别日志
     *
     * @param string $message 日志消息
     * @param array $context 上下文信息
     * @param string $name 日志通道名称
     * @param string $group 日志组名称
     * @param string|null $filename 自定义日志文件名（不包含路径和扩展名）
     */
    public static function critical(string $message, array $context = [], string $name = 'app', string $group = 'default', ?string $filename = null): void
    {
        if ($filename) {
            self::getDynamic($name, $filename, 'critical')->critical($message, $context);
        } else {
            self::get($name, $group)->critical($message, $context);
        }
    }

    /**
     * 记录警报级别日志
     *
     * @param string $message 日志消息
     * @param array $context 上下文信息
     * @param string $name 日志通道名称
     * @param string $group 日志组名称
     * @param string|null $filename 自定义日志文件名（不包含路径和扩展名）
     */
    public static function alert(string $message, array $context = [], string $name = 'app', string $group = 'default', ?string $filename = null): void
    {
        if ($filename) {
            self::getDynamic($name, $filename, 'alert')->alert($message, $context);
        } else {
            self::get($name, $group)->alert($message, $context);
        }
    }

    /**
     * 记录紧急级别日志
     *
     * @param string $message 日志消息
     * @param array $context 上下文信息
     * @param string $name 日志通道名称
     * @param string $group 日志组名称
     * @param string|null $filename 自定义日志文件名（不包含路径和扩展名）
     */
    public static function emergency(string $message, array $context = [], string $name = 'app', string $group = 'default', ?string $filename = null): void
    {
        if ($filename) {
            self::getDynamic($name, $filename, 'emergency')->emergency($message, $context);
        } else {
            self::get($name, $group)->emergency($message, $context);
        }
    }

    /**
     * 记录业务日志
     *
     * @param string $message 日志消息
     * @param array $context 上下文信息
     * @param string $name 日志通道名称
     * @param string|null $filename 自定义日志文件名（不包含路径和扩展名）
     */
    public static function business(string $message, array $context = [], string $name = 'business', ?string $filename = null): void
    {
        if ($filename) {
            self::getDynamic($name, $filename, 'info')->info($message, $context);
        } else {
            self::get($name)->info($message, $context);
        }
    }

    /**
     * 记录访问日志
     *
     * @param string $message 日志消息
     * @param array $context 上下文信息
     * @param string $name 日志通道名称
     * @param string|null $filename 自定义日志文件名（不包含路径和扩展名）
     */
    public static function access(string $message, array $context = [], string $name = 'access', ?string $filename = null): void
    {
        if ($filename) {
            self::getDynamic($name, $filename, 'info')->info($message, $context);
        } else {
            self::get($name)->info($message, $context);
        }
    }

    /**
     * 记录系统日志
     *
     * @param string $message 日志消息
     * @param array $context 上下文信息
     * @param string $name 日志通道名称
     * @param string|null $filename 自定义日志文件名（不包含路径和扩展名）
     */
    public static function system(string $message, array $context = [], string $name = 'system', ?string $filename = null): void
    {
        if ($filename) {
            self::getDynamic($name, $filename, 'info')->info($message, $context);
        } else {
            self::get($name)->info($message, $context);
        }
    }

    /**
     * 记录异常日志
     *
     * @param \Throwable $exception 异常对象
     * @param string $message 额外消息
     * @param array $context 上下文信息
     * @param string $name 日志通道名称
     * @param string|null $filename 自定义日志文件名（不包含路径和扩展名）
     */
    public static function exception(\Throwable $exception, string $message = '', array $context = [], string $name = 'exception', ?string $filename = null): void
    {
        $logMessage = $message ?: $exception->getMessage();
        $context['exception'] = [
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ];
        
        if ($filename) {
            self::getDynamic($name, $filename, 'error')->error($logMessage, $context);
        } else {
            self::get($name)->error($logMessage, $context);
        }
    }
}

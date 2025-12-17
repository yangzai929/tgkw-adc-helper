<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Exception\Handler;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class AppExceptionHandler extends ExceptionHandler
{
    public function __construct(protected StdoutLoggerInterface $logger)
    {
    }

    public function handle(Throwable $throwable, ResponseInterface $response)
    {
        $this->logger->error(sprintf('%s[%s] in %s', $throwable->getMessage(), $throwable->getLine(), $throwable->getFile()));
        $this->logger->error($throwable->getTraceAsString());

        // 2. 区分环境返回不同响应
        if (env('APP_ENV') === 'dev') {
            // 开发环境：构造结构化的错误信息（JSON 格式）
            $errorData = [
                'code' => $throwable->getCode() ?: 500,
                'message' => $throwable->getMessage(),
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
                'trace' => $throwable->getTrace(), // 详细堆栈（数组）
                'trace_string' => $throwable->getTraceAsString(), // 字符串格式的堆栈（可选）
                'previous' => $throwable->getPrevious() ? $throwable->getPrevious()->getMessage() : null, // 前序异常（可选）
            ];

            // 将数组转为 JSON 字符串，传给 SwooleStream
            $errorJson = json_encode($errorData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

            // 返回 JSON 响应，设置正确的 Content-Type
            return $response
                ->withHeader('Server', 'Hyperf')
                ->withHeader('Content-Type', 'application/json; charset=utf-8')
                ->withStatus(500)
                ->withBody(new SwooleStream($errorJson));
        }
        return $response->withHeader('Server', 'Hyperf')->withStatus(500)->withBody(new SwooleStream('Internal Server Error.'));
    }

    public function isValid(Throwable $throwable): bool
    {
        // 不处理验证异常，让专门的验证异常处理器处理
        return ! $throwable instanceof ValidationException;
    }
}

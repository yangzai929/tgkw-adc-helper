<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Helper;

use Exception;
use Hyperf\Context\ApplicationContext;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Psr\Http\Message\ResponseInterface as Psr7ResponseInterface;
use TgkwAdc\Helper\Log\LogHelper;

class ApiResponseHelper
{
    public static function success($data = null, $message = 'success', $code = 0, $httpStatusCode = 200): Psr7ResponseInterface
    {
        $response = ApplicationContext::getContainer()->get(ResponseInterface::class);

        return $response->json([
            'code' => $code,
            'message' => $message,
            'data' => $data,
            'timestamp' => time(),
        ])->withStatus($httpStatusCode);
    }

    public static function error($message = 'error', $error = null, $data = null, $code = 400, $httpStatusCode = 200): Psr7ResponseInterface
    {
        $response = ApplicationContext::getContainer()->get(ResponseInterface::class);

        return $response->json([
            'code' => $code,
            'message' => $message,
            'data' => $data,
            'error' => $error,
            'timestamp' => time(),
        ])->withStatus($httpStatusCode);
    }

    // 远程服务调用成功
    public static function genServiceSuccess($data = null, $messges = 'succcess', $code = 0)
    {
        return [
            'code' => $code,
            'message' => $messges,
            'data' => $data,
            'timestamp' => time(),
        ];
    }

    // 远程服务执行失败
    public static function genServiceError(Exception $exception, $data = null, $messges = 'RPC Service Error', $code = 400)
    {
        LogHelper::error($exception->getMessage(), context: ['trace' => $exception->getTraceAsString()]);

        return [
            'code' => $code,
            'message' => $messges,
            'data' => $data,
            'timestamp' => time(),
        ];
    }

    // 远程服务调用失败
    public static function callServiceError(string $serviceName, Exception $exception)
    {
        LogHelper::error($exception->getMessage(), context: ['rpc_service_name' => $serviceName, 'trace' => $exception->getTraceAsString()]);

        return [
            'code' => $exception->getCode(),
            'message' => $exception->getMessage(),
            'data' => null,
            'error' => [],
            'timestamp' => time(),
        ];
    }
}

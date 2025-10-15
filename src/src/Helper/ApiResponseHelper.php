<?php

namespace TgkwAdc\Helper;

use Hyperf\Context\ApplicationContext;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Psr\Http\Message\ResponseInterface as Psr7ResponseInterface;

class ApiResponseHelper
{
    public static function success($data = null, $message = 'success', $code = 0,$httpStatusCode = 200): Psr7ResponseInterface
    {
        $response = ApplicationContext::getContainer()->get(ResponseInterface::class);

        return $response->json([
            'code' => $code,
            'message' => $message,
            'data' => $data,
            'timestamp' => time(),
        ])->withStatus($httpStatusCode);
    }

    public static function error($message = 'error',$error=null, $data = null, $code = 400,$httpStatusCode = 200): Psr7ResponseInterface
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
}

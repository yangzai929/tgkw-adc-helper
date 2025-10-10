<?php

namespace TgkwAdc\Helper;

use Hyperf\Context\TgkwAdclicationContext;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Psr\Http\Message\ResponseInterface as Psr7ResponseInterface;

class ApiResponseHelper
{
    public static function success($data = null, $message = 'success', $code = 200): Psr7ResponseInterface
    {
        $response = TgkwAdclicationContext::getContainer()->get(ResponseInterface::class);

        return $response->json([
            'code' => $code,
            'message' => $message,
            'data' => $data,
            'timestamp' => time(),
        ]);
    }

    public static function error($message = 'error', $data = null, $code = 400): Psr7ResponseInterface
    {
        $response = TgkwAdclicationContext::getContainer()->get(ResponseInterface::class);

        return $response->json([
            'code' => $code,
            'message' => $message,
            'data' => $data,
            'timestamp' => time(),
        ]);
    }
}

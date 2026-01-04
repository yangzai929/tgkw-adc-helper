<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Exception\Handler;

use Hyperf\Context\ApplicationContext;
use Hyperf\Context\Context;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\JsonRpc\DataFormatter;
use Hyperf\JsonRpc\Packer\JsonEofPacker;
use Hyperf\JsonRpc\ResponseBuilder;
use Hyperf\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TgkwAdc\Exception\BusinessException;
use TgkwAdc\Helper\Log\LogHelper;
use Throwable;

class RpcAppExceptionHandler extends ExceptionHandler
{
    public function handle(Throwable $throwable, ResponseInterface $response)
    {
        $message = 'RPC SERVICE ERROR';
        if ($throwable instanceof BusinessException) {
            $message = $throwable->getMessage();
        }
        $body = [
            'code' => ResponseBuilder::SERVER_ERROR,
            'message' => $message,
            'data' => [
                'class' => $throwable->getFile(),
                'code' => 500,
                'message' => $throwable->getMessage(),
                'error' => $throwable->getTrace(),  // 增加错误信息便于调用方排查
            ],
        ];

        LogHelper::error('OWN RPC SERVICE ERROR', $body);
        $container = ApplicationContext::getContainer();

        /** @var ResponseBuilder $responseBuilder */
        $responseBuilder = make(ResponseBuilder::class, [
            'dataFormatter' => $container->get(DataFormatter::class),
            'packer' => $container->get(JsonEofPacker::class),
        ]);

        // 将RPC参数验证异常转换自定义响应
        return $responseBuilder->buildResponse(
            Context::get(ServerRequestInterface::class),
            $body,
        );
    }

    public function isValid(Throwable $throwable): bool
    {
        // 不处理验证异常，让专门的验证异常处理器处理
        return ! $throwable instanceof ValidationException;
    }
}

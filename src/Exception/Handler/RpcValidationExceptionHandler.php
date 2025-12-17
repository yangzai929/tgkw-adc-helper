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
use TgkwAdc\Helper\LocaleHelper;
use TgkwAdc\Helper\Log\LogHelper;
use Throwable;

class RpcValidationExceptionHandler extends ExceptionHandler
{
    public function handle(Throwable $throwable, ResponseInterface $response)
    {
        if ($throwable instanceof ValidationException) {
            $this->stopPropagation();

            // 翻译验证异常消息
            $message = LocaleHelper::trans('validation.invalid_data');
            // 确保验证器使用正确的语言环境
            $validator = $throwable->validator;
            $validator->getTranslator()->setLocale(LocaleHelper::getCurrentLocale());
            $error = $validator->errors()->toArray();
            LogHelper::info('RPC ValidationException', context: ['error' => $error, 'locale' => LocaleHelper::getCurrentLocale()]);
            $error = array_map(function ($item) {
                return $item[0];
            }, $error);

            // 获取首条错误信息
            $body = [
                'code' => ResponseBuilder::INVALID_PARAMS,
                'message' => $message,
                'data' => [
                    'class' => ValidationException::class,
                    'code' => $throwable->status,
                    'message' => $message,
                    'error' => $error,  // 增加错误信息便于调用方排查
                ],
            ];

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
        return $response;
    }

    public function isValid(Throwable $throwable): bool
    {
        // 只处理 ValidationException，让 BusinessException 等其他异常继续传播
        return $throwable instanceof ValidationException;
    }
}

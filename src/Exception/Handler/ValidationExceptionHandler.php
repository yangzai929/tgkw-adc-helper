<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Exception\Handler;

use Hyperf\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;
use TgkwAdc\Helper\ApiResponseHelper;
use TgkwAdc\Helper\LocaleHelper;
use Throwable;

class ValidationExceptionHandler extends BaseExceptionHandler
{
    public function handle(Throwable $throwable, ResponseInterface $response): ResponseInterface
    {
        if ($throwable instanceof ValidationException) {
            $this->stopPropagation(); // 阻止继续向下传播异常

            // 翻译验证异常消息
            $message = LocaleHelper::trans('validation.invalid_data');

            // 确保验证器使用正确的语言环境
            $validator = $throwable->validator;
            $validator->getTranslator()->setLocale(LocaleHelper::getCurrentLocale());
            $error = $validator->errors()->toArray();
            $error = array_map(function ($item) {
                return $item[0];
            }, $error);
            return ApiResponseHelper::error(
                message: $message,
                error: $error,
                code: $throwable->status,
                data: ['show_error' => true],
            );
        }

        return $response;
    }

    public function isValid(Throwable $throwable): bool
    {
        return $throwable instanceof ValidationException;
    }
}

<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Trait;

use Psr\Http\Message\ResponseInterface;
use TgkwAdc\Helper\ApiResponseHelper;
use TgkwAdc\Helper\Log\LogHelper;

trait JsonRpcCall
{
    /**
     * 控制器中调用RPC 用于处理RPC返回数据,同时组装api 响应.
     * @param mixed $response
     * @param mixed $method
     * @return array|null[]|ResponseInterface
     */
    public function handleRpcResponse($response, ?string $resourceClass = null, $method = 'make')
    {
        LogHelper::debug('RPC Response:', [$response]);
        $resData = []; // 提前初始化变量，避免未定义警告
        if (isset($response['code'], $response['data'], $response['message']) && $response['code'] < 0) {
            $error = $response['data']['error'] ?? null;
            $code = $response['code'];
            // 处理验证异常
            $validationExceptionClass = 'Hyperf\Validation\ValidationException';
            if (isset($response['data']['class']) && $response['data']['class'] == $validationExceptionClass) {
                $resData['show_error'] = true;
                $code = $response['data']['code'];
            }
            $re = [
                'message' => $response['message'],
                'code' => $code,
                'error' => $error,
                'data' => $resData,
            ];
            // 参数解包
            return ApiResponseHelper::error(...$re);
        }

        if ($resourceClass) {
            // 校验资源类和方法是否存在，避免调用不存在的方法导致报错
            if (class_exists($resourceClass) && method_exists($resourceClass, $method)) {
                $formattedData = $resourceClass::$method($response);
                return ApiResponseHelper::success($formattedData);
            }
            // 兜底：如果资源类/方法不存在，直接debug返回原始数据
            $formattedData = $response;
            return ApiResponseHelper::debug($formattedData);
        }
        return ApiResponseHelper::success();
    }

    /**
     * 非控制中调用RPC 检查是否有错误，不做数据处理，由调用方自行处理.
     * @param mixed $response
     * @return bool
     */
    public function hasError($response)
    {
        if (isset($response['code']) && $response['code'] < 0) {
            return true;  // 有异常，可以查看response
        }
        return false; // 无异常response 即为data
    }
}

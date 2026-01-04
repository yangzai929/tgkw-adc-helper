<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Helper;

use Hyperf\Context\ApplicationContext;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Paginator\LengthAwarePaginator;
use Hyperf\Resource\Json\JsonResource;
use Hyperf\Resource\Json\ResourceCollection;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Psr7ResponseInterface;
use TgkwAdc\Annotation\EnumCodeInterface;

class ApiResponseHelper
{
    /**
     * 成功响应（强制数据必须是资源类/资源集合）.
     *
     * @param JsonResource|ResourceCollection $data 资源类实例或资源集合
     * @param string $message 响应信息
     * @param int $code HTTP 状态码
     * @param mixed $httpStatusCode
     * @throws InvalidArgumentException 若数据不是资源类
     */
    public static function success($data = null, $message = 'success', $code = 0, $httpStatusCode = 200): Psr7ResponseInterface
    {
        $response = ApplicationContext::getContainer()->get(ResponseInterface::class);

        // 1. 数据非空时，强制校验必须是资源类/资源集合
        if (! is_null($data)) {
            // 校验是否为单个资源（JsonResource 实例）
            $isSingleResource = $data instanceof JsonResource;
            // 校验是否为资源集合（ResourceCollection 实例）
            $isCollectionResource = $data instanceof ResourceCollection;
            // 校验是否为「分页资源集合」（JsonResource::collection($paginator) 生成的实例）
            $isPaginatorResource = $data instanceof LengthAwarePaginator && isset($data->collects) && is_subclass_of($data->collects, JsonResource::class);

            // 若都不满足，抛出异常
            if (! $isSingleResource && ! $isCollectionResource && ! $isPaginatorResource) {
                return $response->json([
                    'error' => '数据格式错误。API 响应数据必须是资源类实例（继承 TgkwAdc\Resource\BaseResource）或资源集合（继承 TgkwAdc\Resource\BaseCollection）',
                ])->withStatus(400);
            }

            $formattedData = $data;
        } else {
            // 数据为空时，默认返回空数组
            $formattedData = null;
        }

        return $response->json([
            'code' => $code,
            'message' => $message,
            'data' => $formattedData,
            'timestamp' => time(),
        ])->withStatus($httpStatusCode);
    }

    public static function error($message = 'error', $error = null, $data = null, $code = 400, $httpStatusCode = 400): Psr7ResponseInterface
    {
        $response = ApplicationContext::getContainer()->get(ResponseInterface::class);

        if ($code instanceof EnumCodeInterface) {
            $message = $code->getI18nMsg();
            $code = $code->getCode();
        }

        return $response->json([
            'code' => $code,
            'message' => $message,
            'data' => $data,
            'error' => $error,
            'timestamp' => time(),
        ])->withStatus($httpStatusCode);
    }

    public static function debug($data = null)
    {
        return [
            'data' => $data,
        ];
    }
}

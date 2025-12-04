<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Middleware;

use Hyperf\Amqp\Producer;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TgkwAdc\Amqp\Producer\OperationLogProducer;
use TgkwAdc\Constants\GlobalConstants;
use TgkwAdc\Helper\JwtHelper;
use TgkwAdc\Helper\Log\LogHelper;
use TgkwAdc\Utils\IpTool;

class OperationLogMiddleware implements MiddlewareInterface
{
    protected ContainerInterface $container;

    protected RequestInterface $request;

    protected HttpResponse $response;

    public function __construct(ContainerInterface $container, HttpResponse $response, RequestInterface $request)
    {
        $this->container = $container;
        $this->response = $response;
        $this->request = $request;
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $result = $handler->handle($request);

        $isDownload = false;
        if (! empty($result->getHeader('content-description')) && ! empty($result->getHeader('content-transfer-encoding'))) {
            $isDownload = true;
        }

        $ip = IpTool::getRealIp($request);
        $operationLog = [
            'tenant_id' => 0,
            'time' => date('Y-m-d H:i:s', $request->getServerParams()['request_time']),
            'method' => $request->getServerParams()['request_method'],
            'router' => $request->getServerParams()['path_info'],
            'protocol' => $request->getServerParams()['server_protocol'],
            'ip' => $ip,
            'service_name' => env('APP_NAME'),
            'request_data' => $this->request->all(),
            'response_code' => $result->getStatusCode(),
            'response_data' => $isDownload ? '文件下载' : $result->getBody()->getContents(),
        ];

        $type = '';
        if ($this->request->header(GlobalConstants::SYS_TOKEN_KEY)) {
            $type = GlobalConstants::SYS_TOKEN_TYPE;
        } elseif ($this->request->header(GlobalConstants::ORG_TOKEN_KEY)) {
            $type = GlobalConstants::ORG_TOKEN_TYPE;
        }

        // 获取用户登录信息.
        $user_data = $this->getUserData($request, $type);
        if ($user_data) {
            $operationLog['user_data'] = $user_data;
            if ($type == GlobalConstants::ORG_TOKEN_TYPE) {
                $operationLog['tenant_id'] = $user_data['tenant_id'] ?: 0;
            }
        } else {
            return $result;
        }

        // GET请求的不存
        if ($operationLog['method'] == 'GET') {
            return $result;
        }

        // 将日志存储到amqp中
        $message = new OperationLogProducer($operationLog);
        $producer = container_get(Producer::class);
        $proResult = $producer->produce($message);
        LogHelper::debug('OperationLogProducer amqp', [$proResult]);

        return $result;
    }

    /**
     * 获取用户登录信息.
     * @param mixed $type
     * @param mixed $request
     */
    public function getUserData($request, $type): array
    {
        $userData = [];
        if (! empty($type)) {
            $jwtData = JwtHelper::getPayloadFromRequest($request, $type);
            if ($jwtData) {
                $userData = [
                    'id' => $jwtData->id,
                    'account' => $jwtData->account ?? '',
                    'real_name' => $jwtData->real_name ?? '',
                    'mobile' => $jwtData->mobile ?? '',
                    'email' => $jwtData->email ?? '',
                    'tenant_id' => $jwtData->tenant_id ?? 0,
                    'origin' => strtolower($type),
                ];
            }
        }

        return $userData;
    }
}

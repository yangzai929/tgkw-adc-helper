<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\JsonRpc\User;

use Exception;
use Hyperf\RpcClient\AbstractServiceClient;
use TgkwAdc\Helper\ApiResponseHelper;

class UserServiceConsumer extends AbstractServiceClient implements UserServiceInterface
{
    /**
     * 定义对应服务提供者的服务名称.
     */
    protected string $serviceName = 'UserService';

    /**
     * 定义对应服务提供者的服务协议.
     */
    protected string $protocol = 'jsonrpc-http';

    public function test(array $param, string $micro): array
    {
        return $this->__request(__FUNCTION__, compact('param', 'micro'));
    }

    public function checkAccessPermission(array $param): array
    {
        try {
            return $this->__request(__FUNCTION__, compact('param'));
        } catch (Exception $exception) {
            return ApiResponseHelper::callServiceError($this->serviceName, $exception);
        }
    }

    public function addMenu(array $param): array
    {
        try {
            return $this->__request(__FUNCTION__, compact('param'));
        } catch (Exception $exception) {
            return ApiResponseHelper::callServiceError($this->serviceName, $exception);
        }
    }

    public function getMenu(array $nowUser, string $micro): array
    {
        try {
            return $this->__request(__FUNCTION__, compact('nowUser', 'micro'));
        } catch (Exception $exception) {
            return ApiResponseHelper::callServiceError($this->serviceName, $exception);
        }
    }

    public function getUserInfo(int $userId): array
    {
        try {
            return $this->__request(__FUNCTION__, compact('userId', 'micro'));
        } catch (Exception $exception) {
            return ApiResponseHelper::callServiceError($this->serviceName, $exception);
        }
    }

    public function getUsers(array $userIds): array
    {
        try {
            return $this->__request(__FUNCTION__, compact('userIds', 'micro'));
        } catch (Exception $exception) {
            return ApiResponseHelper::callServiceError($this->serviceName, $exception);
        }
    }
}

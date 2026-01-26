<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\JsonRpc\User;

use Hyperf\RpcClient\AbstractServiceClient;

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

    public function checkAccessPermission(array $param): array
    {
        return $this->__request(__FUNCTION__, compact('param'));
    }

    public function addMenu(array $param): array
    {
        return $this->__request(__FUNCTION__, compact('param'));
    }

    public function getMenu(array $nowUser, string $micro = ''): array
    {
        return $this->__request(__FUNCTION__, compact('nowUser', 'micro'));
    }

    public function getUserInfo(int $userId, string $micro = ''): array
    {
        return $this->__request(__FUNCTION__, compact('userId', 'micro'));
    }

    public function getUsers(array $userIds, string $micro = ''): array
    {
        return $this->__request(__FUNCTION__, compact('userIds', 'micro'));
    }

    public function getAppid(array $param): int
    {
        return $this->__request(__FUNCTION__, compact('param'));
    }
}

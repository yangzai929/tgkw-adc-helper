<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\JsonRpc\Public;

use Hyperf\RpcClient\AbstractServiceClient;

class SystemServiceConsumer extends AbstractServiceClient implements SystemServiceInterface
{
    /**
     * 处理系统总后的菜单收集和总后台菜单权限控制
     */
    protected string $serviceName = 'SystemService';

    /**
     * 定义对应服务提供者的服务协议.
     */
    protected string $protocol = 'jsonrpc-http';

    public function addMenu(array $param): array
    {
        return $this->__request(__FUNCTION__, compact('param'));
    }

    public function checkAccessPermission(array $param): array
    {
        return $this->__request(__FUNCTION__, compact('param'));
    }
}

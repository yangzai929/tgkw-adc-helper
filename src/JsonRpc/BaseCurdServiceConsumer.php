<?php

namespace TgkwAdc\JsonRpc;

use Hyperf\RpcClient\AbstractServiceClient;

abstract class BaseCurdServiceConsumer extends AbstractServiceClient implements BaseCurdServiceInterface
{
    // 定义对应服务提供者的服务协议和地址
    protected string $serviceName;

    protected string $protocol = 'jsonrpc-http';

    public function columns(): array
    {
        return $this->__request(__FUNCTION__, []);
    }

    public function index(array $params): array
    {
        return $this->__request(__FUNCTION__, compact('params'));
    }

    public function store(array $params): array
    {
        return $this->__request(__FUNCTION__, compact('params'));
    }

    public function show(array $params): array
    {
        return $this->__request(__FUNCTION__, compact('params'));
    }

    public function update(array $params): array
    {
        return $this->__request(__FUNCTION__, compact('params'));
    }

    public function destroy(array $params): array
    {
        return $this->__request(__FUNCTION__, compact('params'));
    }
}
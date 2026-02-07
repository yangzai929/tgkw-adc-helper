<?php

namespace TgkwAdc\JsonRpc\Hr;

use Hyperf\RpcClient\AbstractServiceClient;
use TgkwAdc\JsonRpc\Public\SystemServiceInterface;

class HrServiceConsumer  extends AbstractServiceClient implements HrServiceInterface
{

    protected string $serviceName = 'HrService';

    protected string $protocol = 'jsonrpc-http';

    public function call(string $method, array $param): array
    {
        if (! $method) {
            throw new \Exception('method不存在，请传参');
        }

        return $this->__request(__FUNCTION__, compact('method', 'param'));
    }

    public function getEmployeeByUserId(int $userId, int $tenantId): array
    {
        return $this->__request(__FUNCTION__, compact('userId', 'tenantId'));
    }


    public function getEmployeesByUserIds(array $userIds, int $tenantId): array
    {
        return $this->__request(__FUNCTION__, compact('userIds', 'tenantId'));
    }


    /**
     * 根据组织id 和 租户id 获取组织信息及该组织下的员工信息.
     */
    public function getOrganizationWithEmployeesByOrgId(int $orgId, int $tenantId): array{
        return $this->__request(__FUNCTION__, compact('orgId', 'tenantId'));
    }

    /**
     * 根据批量组织id 和 租户id 获取组织信息.
     */
    public function getOrganizationsByOrgIds(array $orgIds, int $tenantId): array{
        return $this->__request(__FUNCTION__, compact('orgIds', 'tenantId'));
    }

    /**
     * 获取所有组织信息.
     */
    public function getAllOrganizations(int $tenantId): array{
        return $this->__request(__FUNCTION__, compact( 'tenantId'));

    }

    /**
     * 获取所有组织信息及组织下的员工信息.
     */
    public function getAllOrganizationsWithEmployees(int $tenantId): array{
        return $this->__request(__FUNCTION__, compact( 'tenantId'));
    }

}
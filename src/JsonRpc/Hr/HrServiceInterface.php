<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\JsonRpc\Hr;

interface HrServiceInterface
{
    /**
     * 根据用户id 和 租户id 获取员工信息.
     */
    public function getEmployeeByUserId(int $userId, int $tenantId): array;

    /**
     * 根据批量用户id 和 租户id 获取员工信息.
     */
    public function getEmployeesByUserIds(array $userIds, int $tenantId): array;

    /**
     * 根据组织id 和 租户id 获取组织信息及该组织下的员工信息.
     */
    public function getOrganizationWithEmployeesByOrgId(int $orgId, int $tenantId): array;

    /**
     * 根据批量组织id 和 租户id 获取组织信息.
     */
    public function getOrganizationsByOrgIds(array $orgIds, int $tenantId): array;

    /**
     * 获取所有组织信息.
     */
    public function getAllOrganizations(int $tenantId): array;

    /**
     * 获取所有组织信息及组织下的员工信息.
     */
    public function getAllOrganizationsWithEmployees(int $tenantId): array;
}

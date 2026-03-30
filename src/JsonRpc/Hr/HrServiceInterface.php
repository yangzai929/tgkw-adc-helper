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
    public function getEmployeeByUserId(int $userId, int $tenantId, bool $withRelations = true): array;

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

    /**
     * 获取租户下所有岗位信息.
     */
    public function getAllPositions(int $tenantId): array;

    /**
     * 根据用户ID和租户ID查找该用户的直接上级（所在部门主要负责人，排除自身）.
     *
     * @return array 上级员工信息，无上级时返回空数组
     */
    public function getSuperiorByUserId(int $userId, int $tenantId): array;

    /**
     * 根据用户ID和租户ID查找该用户所在部门的所有负责人（主要+次要）.
     *
     * @return array 部门负责人员工信息列表
     */
    public function getDepartmentLeadersByUserId(int $userId, int $tenantId): array;

    /**
     * 根据用户ID和租户ID查找连续多级上级（主要负责人链，从直接上级向上递推）.
     *
     * 每条记录附带 level 字段，1 为直接上级，数字越大越靠上。
     *
     * @param int $maxLevel 最大递推层数，默认10
     * @return array 有序上级员工信息列表（含 level 字段）
     */
    public function getMultiLevelSuperiorsByUserId(int $userId, int $tenantId, int $maxLevel = 10): array;

    /**
     * 根据用户ID和租户ID按组织层级向上查找连续多级部门负责人.
     *
     * level=1 为当前部门负责人，level=2 为父部门负责人，以此类推。
     * 每条记录附带 level 字段。
     *
     * @param int $maxLevel 最大向上层数，默认10
     * @return array 各层级部门负责人列表（含 level 字段）
     */
    public function getMultiLevelDepartmentLeadersByUserId(int $userId, int $tenantId, int $maxLevel = 10): array;

    /**
     * 根据成员姓名关键字和租户ID模糊查询成员及其所在部门.
     */
    public function searchEmployeesByName(string $keyword, int $tenantId): array;

    /**
     * 获取租户组织统计数据（员工数、公司数、部门数、未加入人数）.
     */
    public function getOrganizationStats(int $tenantId): array;
}

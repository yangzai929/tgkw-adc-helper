<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\JsonRpc\User;

interface UserServiceInterface
{
    /**
     * 校验用户访问权限.
     *
     * @param array $param 权限校验参数
     * @return array 权限校验结果
     */
    public function checkAccessPermission(array $param): array;

    /**
     * 新增菜单及按钮权限配置.
     *
     * @param array $param 菜单配置参数
     * @return array 操作结果
     */
    public function addMenu(array $param): array;

    /**
     * 获取当前用户可见菜单.
     *
     * @param array $nowUser 当前用户信息
     * @param string $micro 微服务标识
     * @return array 菜单数据
     */
    public function getMenu(array $nowUser, string $micro): array;

    /**
     * 获取指定用户信息.
     *
     * @param int $userId 用户ID
     * @return array 用户信息
     */
    public function getUserInfo(int $userId): array;

    public function getTenantInfo(int $tenant_id): array;

    /**
     * 批量获取用户信息.
     *
     * @param array $userIds 用户ID数组
     * @return array 用户信息列表
     */
    public function getUsers(array $userIds): array;

    /**
     * 根据参数获取应用ID.
     *
     * @param array $param 查询参数
     * @return int 应用ID
     */
    public function getAppid(array $param): int;

    /**
     * 根据联系方式获取或创建用户.
     *
     * @param string $contact 联系方式（手机号/邮箱等）
     * @return array 用户信息
     */
    public function getOrCreateUserByContact(string $contact): array;

    /**
     * 绑定用户与租户关系.
     *
     * @param int $userId 用户ID
     * @param int $tenantId 租户ID
     * @return array 绑定结果
     */
    public function bindUserTenant(int $userId, int $tenantId): array;

    /**
     * 获取当前租户下的角色列表.
     *
     * @param int $tenantId 租户ID
     * @return array 角色列表
     */
    public function getCurrentTenantRoles(int $tenantId): array;

    /**
     * 根据角色ID列表查找用户（支持多角色，返回并集去重）.
     *
     * @param int[]|string[] $roleIds 角色ID列表
     * @param int $tenantId 租户ID
     * @return array 用户列表
     */
    public function getUsersByRoles(array $roleIds, int $tenantId): array;

    /**
     * 根据批量角色ID查询角色信息.
     *
     * @param int[]|string[] $roleIds 角色ID列表
     * @param int $tenantId 租户ID
     * @return array 角色信息列表
     */
    public function getRolesByIds(array $roleIds, int $tenantId): array;

    /*
    * 获取指定用户的人员管理范围
    */
    public function getUserPermiScope(int $userId, int $tenantId): array;
    public function getUsersByRoleName(string $roleName, int $tenantId): array;



}

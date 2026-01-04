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
    public function checkAccessPermission(array $param): array;

    public function addMenu(array $param): array;

    /**
     * 获取当前用户信息.
     */
    public function getMenu(array $nowUser, string $micro): array;

    /**
     * 获取当前用户信息.
     * @param int $userId 用户id
     */
    public function getUserInfo(int $userId): array;

    /**
     * 批量获取用户信息.
     * @param array $userIds 用户id数组
     */
    public function getUsers(array $userIds): array;
}

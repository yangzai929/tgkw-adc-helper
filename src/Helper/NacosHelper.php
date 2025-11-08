<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Helper;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use TgkwAdc\Helper\Log\LogHelper;

class NacosHelper
{
    protected string $host;

    protected int $port;

    protected string $username;

    protected string $password;

    protected string $namespaceId;

    protected string $group;

    protected Client $client;

    public function __construct()
    {
        $this->host = config('config_center.drivers.nacos.client.host');
        $this->port = config('config_center.drivers.nacos.client.port');
        $this->username = config('config_center.drivers.nacos.client.username');
        $this->password = config('config_center.drivers.nacos.client.password');
        $this->namespaceId = config('config_center.drivers.nacos.client.namespace_id');
        $this->group = config('config_center.drivers.nacos.client.group_name');

        $this->client = new Client([
            'base_uri' => sprintf('http://%s:%d', $this->host, $this->port),
            'timeout' => 5,
            'verify' => false, // 忽略证书错误
        ]);
    }

    /**
     * 写入配置到 Nacos.
     */
    public function set(string $dataId, string $content, string $type = 'json'): bool
    {
        try {
            $response = $this->client->post('/nacos/v1/cs/configs', [
                'form_params' => [
                    'dataId' => $dataId,
                    'group' => $this->group,
                    'tenant' => $this->namespaceId,
                    'type' => $type,
                    'content' => $content,
                    'username' => $this->username,
                    'password' => $this->password,
                ],
            ]);

            LogHelper::info('写入配置到 Nacos', [$this->group, $this->namespaceId]);

            return $response->getStatusCode() === 200;
        } catch (GuzzleException $e) {
            return false;
        }
    }

    /**
     * 删除配置.
     */
    public function delete(string $dataId): bool
    {
        try {
            $response = $this->client->delete('/nacos/v1/cs/configs', [
                'query' => [
                    'dataId' => $dataId,
                    'group' => $this->group,
                    'tenant' => $this->namespaceId,
                    'username' => $this->username,
                    'password' => $this->password,
                ],
            ]);

            return $response->getStatusCode() === 200;
        } catch (GuzzleException $e) {
            return false;
        }
    }
}

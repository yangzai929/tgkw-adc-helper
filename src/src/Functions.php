<?php

declare(strict_types=1);

use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Redis\RedisFactory;

if (!function_exists('cfg')) {
    /**
     * 获取配置
     *
     * @param string|null $key 配置键，例如 'jwt.secret'
     * @param mixed $default 默认值
     * @return mixed
     */
    function cfg(?string $key = null, $default = null)
    {
        /** @var ConfigInterface $config */
        $config = ApplicationContext::getContainer()->get(ConfigInterface::class);

        if ($key === null) {
            return $config;
        }

        return $config->get($key, $default);
    }
}

if (! function_exists('redis')) {
    /**
     * 获取 Redis 客户端（连接池名可选，默认 default）。
     *
     * @param string|null $pool 连接池名称，如 'default'
     * @return mixed Redis 客户端实例（PhpRedis 代理）
     */
    function redis(?string $pool = 'default')
    {
        /** @var RedisFactory $factory */
        $factory = ApplicationContext::getContainer()->get(RedisFactory::class);
        return $factory->get($pool ?? 'default');
    }
}

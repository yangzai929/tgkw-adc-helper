<?php

declare(strict_types=1);

use Hyperf\Utils\ApplicationContext;
use Hyperf\Contract\ConfigInterface;

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

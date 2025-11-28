<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Resource;

use Hyperf\Context\Context;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Resource\Json\JsonResource as BaseJsonResource;

// 自定义基础资源类，兼容数组和模型
abstract class JsonResource extends BaseJsonResource
{
    // 存储原始输入数据（可能是模型或数组）
    protected $originData;

    public function __construct($resource)
    {
        parent::__construct($resource);
        // 保存原始数据，供后续访问
        $this->originData = $resource;
    }

    // 重写 __get 魔术方法，支持同时访问数组键和对象属性
    public function __get($key)
    {
        // 1. 优先尝试对象属性访问（模型实例）
        if (is_object($this->originData) && property_exists($this->originData, $key)) {
            return $this->originData->{$key};
        }

        // 2. 再尝试数组访问（数组/关联数组）
        if (is_array($this->originData) && array_key_exists($key, $this->originData)) {
            return $this->originData[$key];
        }

        // 3. 模型关联/访问器（如果是模型，调用父类 __get 保持原有功能）
        if (is_object($this->originData)) {
            return parent::__get($key);
        }

        // 4. 无该字段，返回 null
        return null;
    }

    // 可选：支持数组 isset 判断（避免报错）
    public function __isset($key)
    {
        if (is_object($this->originData)) {
            return property_exists($this->originData, $key) || isset($this->originData->{$key});
        }
        if (is_array($this->originData)) {
            return array_key_exists($key, $this->originData);
        }
        return false;
    }

    // 资源类内部获取请求（复用之前的逻辑）
    protected function getRequest(): ?RequestInterface
    {
        return Context::get(RequestInterface::class);
    }
}

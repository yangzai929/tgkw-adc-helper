<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 *
 */

namespace TgkwAdc\Resource;

use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Resource\Json\ResourceCollection;

/**
 * 基础集合资源类
 * 统一分页、统计、附加信息与集合处理操作.
 */
abstract class BaseCollection extends ResourceCollection
{
    #[Inject]
    protected RequestInterface $request;

    /**
     * 是否包含分页信息.
     */
    protected bool $withPagination = true;

    /**
     * 设置是否包含分页信息.
     */
    public function withPagination(bool $withPagination = true): self
    {
        $this->withPagination = $withPagination;

        return $this;
    }

    /**
     * 转换为数组（自动附加分页信息与附加数据）.
     */
    public function toArray(): array
    {
        $data = parent::toArray();

        // 保证输出结构稳定
        if (! isset($data['data']) && isset($data[0])) {
            $data = ['items' => $data];
        }

        if ($this->withPagination && $this->isPaginator()) {
            $data['pagination'] = $this->getPaginationInfo();
        }

        if (! empty($this->additional)) {
            $data = array_merge($data, $this->additional);
        }

        return $data;
    }

    /**
     * 添加额外数据.
     */
    public function withData(array $data): self
    {
        $this->additional = array_merge($this->additional ?? [], $data);

        return $this;
    }

    /**
     * 添加元数据.
     */
    public function withMeta(array $meta): self
    {
        $this->additional = array_merge($this->additional ?? [], ['meta' => $meta]);

        return $this;
    }

    /**
     * 添加统计信息.
     */
    public function withStats(array $stats): self
    {
        $this->additional = array_merge($this->additional ?? [], ['stats' => $stats]);

        return $this;
    }

    /**
     * 动态追加附加信息.
     */
    public function append(string $key, mixed $value): self
    {
        $this->additional[$key] = $value;

        return $this;
    }

    /**
     * 过滤集合数据.
     */
    public function filterCollection(callable $callback): self
    {
        $this->collection = $this->collection->filter($callback);

        return $this;
    }

    /**
     * 排序集合数据.
     */
    public function sort(callable $callback): self
    {
        $this->collection = $this->collection->sort($callback)->values();

        return $this;
    }

    /**
     * 限制集合数量.
     */
    public function limit(int $limit): self
    {
        $this->collection = $this->collection->take($limit);

        return $this;
    }

    /**
     * 跳过指定数量.
     */
    public function skip(int $count): self
    {
        $this->collection = $this->collection->skip($count);

        return $this;
    }


    /**
     * 转换为简单数组格式.
     */
    public function toSimpleArray(): array
    {
        return $this->collection->values()->toArray();
    }

    /**
     * 转换为键值对格式.
     */
    public function toKeyValue(string $key, string $value): array
    {
        return $this->collection->pluck($value, $key)->toArray();
    }

    /**
     * 分组数据.
     */
    public function groupBy(string $key): array
    {
        return $this->collection->groupBy($key)->toArray();
    }

    /**
     * 判断当前资源是否为分页器.
     */
    protected function isPaginator(): bool
    {
        return method_exists($this->resource, 'total')
            && method_exists($this->resource, 'currentPage');
    }

    /**
     * 获取分页信息.
     */
    protected function getPaginationInfo(): array
    {
        if (! $this->isPaginator()) {
            return [];
        }

        $pagination = [
            'total' => $this->total(),
            "current_items" => $this->count(),
            'current_page' => $this->currentPage(),
            'per_page' => $this->perPage(),
            'last_page' => $this->lastPage(),
            'has_more' => $this->hasMorePages(),
        ];


        return $pagination;
    }


}

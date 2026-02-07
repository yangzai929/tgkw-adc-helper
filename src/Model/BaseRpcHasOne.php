<?php

namespace TgkwAdc\Model;

use Hyperf\Database\Model\Builder;
use Hyperf\Database\Model\Collection;
use Hyperf\Database\Model\Model;
use Hyperf\Database\Model\Relations\HasOne;
use TgkwAdc\Helper\Log\LogHelper;

class BaseRpcHasOne  extends HasOne
{
    /**
     * @var array [ServiceClass, method, ...extraArgs]
     */
    protected array $rpcConfig;

    public function __construct(Builder $query, Model $parent, string $foreignKey, string $localKey, array $rpcConfig)
    {
        $this->rpcConfig = $rpcConfig;
        parent::__construct($query, $parent, $foreignKey, $localKey);
    }

    /**
     * with() 预加载触发，返回空集合（实际数据在 match 中获取）.
     */
    public function getEager()
    {
        return $this->related->newCollection();
    }

    /**
     * 懒加载：$model->employee 直接访问时触发.
     */
    public function getResults()
    {
        $id = $this->getParent()->{$this->foreignKey};
        $data = $this->getRpcData([$id]);
        return current($data) ?: null;
    }

    /**
     * 预加载匹配：批量获取 RPC 数据并挂到每个父模型上.
     */
    public function match(array $models, Collection $results, $relation)
    {
        if (empty($models)) {
            return $models;
        }

        $ids = array_unique(array_filter(array_map(fn ($m) => $m->{$this->foreignKey} ?? null, $models)));

        if (empty($ids)) {
            return $models;
        }

        $data = $this->getRpcData($ids);
        $data = array_column($data, null, $this->localKey);

        foreach ($models as $model) {
            $key = $model->{$this->foreignKey};
            $model->setRelation($relation, $data[$key] ?? null);
        }

        return $models;
    }

    /**
     * 调用 RPC 服务获取数据.
     * rpcConfig 格式: [ServiceClass, method, ...extraArgs]
     * 最终调用: ServiceClass->method($ids, ...extraArgs)
     */
    protected function getRpcData(array $ids): array
    {
        try {
            [$serviceClass, $method] = $this->rpcConfig;
            $extraArgs = array_slice($this->rpcConfig, 2);
            return call_user_func([make($serviceClass), $method], $ids, ...$extraArgs);
        } catch (\Throwable $e) {
            LogHelper::error('RpcHasOne调用异常: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return [];
        }
    }
}
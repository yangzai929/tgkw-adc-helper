<?php

namespace TgkwAdc\Resource;

class BaseRpcCollection extends BaseCollection {
    protected string $ResourceClass;

    /**
     * 指定集合内每条数据的资源类，确保 items 使用该格式输出。
     */
    public function toArray(): array
    {
        // 从资源中获取原始数据
        $dataArray = $this->resource;

        // 获取 items 数组
        $items = $dataArray['items'] ?? [];

        // 获取完整的资源类名（包括命名空间）
        $resourceClassName = $this->getResourceClassName();

        // 将 items 转换为资源类
        $data['items'] = collect($items)->map(function ($item) use ($resourceClassName) {
            return $resourceClassName::make($item);
        });

        // 获取 pagination
        $data['pagination'] = $dataArray['pagination'] ?? [];

        return $data;
    }

    /**
     * 获取完整的资源类名（包括命名空间）.
     */
    protected function getResourceClassName(): string
    {
        // 如果 ResourceClass 已经是完整类名，直接返回
        if (strpos($this->ResourceClass, '\\') !== false) {
            return $this->ResourceClass;
        }

        // 否则，拼接当前类的命名空间
        $currentNamespace = get_class($this);
        $namespaceParts = explode('\\', $currentNamespace);
        array_pop($namespaceParts); // 移除类名
        $namespace = implode('\\', $namespaceParts);

        return $namespace . '\\' . $this->ResourceClass;
    }
}
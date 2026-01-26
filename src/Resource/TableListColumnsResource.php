<?php

namespace TgkwAdc\Resource;

/**
 * 服务内数据源格式化 ，RPC服务返回的数据不通过此基类格式化
 */
class TableListColumnsResource extends BaseResource
{
    public function toArray(): array
    {
        $columns = [];
        foreach ($this->columns ?? [] as $column) {
            $formattedColumn = [
                'key' => $column['key'] ?? '',
                'i18n_txt' => $column['i18n_txt'] ?? [],
                'i18n_key' => $column['i18n_key'] ?? '',
            ];

            if (isset($column['value_map_key'])) {
                $formattedColumn['value_map_key'] = $column['value_map_key'];
            }

            $columns[] = $formattedColumn;
        }

        return [
            'columns' => $columns,
            'value_maps' => $this->value_maps ?? [],
        ];
    }
}
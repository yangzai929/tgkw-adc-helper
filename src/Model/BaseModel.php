<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Model;

use Carbon\Carbon;
use Hyperf\Database\Model\SoftDeletes;
use Hyperf\DbConnection\Model\Model;
use Hyperf\ModelCache\Cacheable;
use Hyperf\ModelCache\CacheableInterface;
use RuntimeException;

class BaseModel extends Model implements CacheableInterface
{
    use Cacheable;

    protected ?string $dateFormat = 'U';

    public function delete()
    {
        // 第一步：判断是否启用软删除
        if (in_array(SoftDeletes::class, class_uses_recursive($this))) {
            // 场景1：启用软删除 → 更新 deleted_at
            $deletedAtColumn = $this->getDeletedAtColumn(); // 安全调用
            $this->{$deletedAtColumn} = Carbon::now();
            $this->save();
            return true;
        }
        // 场景2：未启用软删除 → 执行原生物理删除逻辑
        return parent::delete();
    }

    //  重写恢复软删除的逻辑（清空 deleted_at）
    public function restore()
    {
        // 先判断是否启用软删除，避免无软删除时调用报错
        if (! in_array(SoftDeletes::class, class_uses_recursive($this))) {
            throw new RuntimeException('该模型未启用软删除，无法恢复');
        }
        $deletedAtColumn = $this->getDeletedAtColumn();
        $this->{$deletedAtColumn} = null;
        $this->save();
        return true;
    }

    private function setShanghaiDateTime(string $field, $value): void
    {
        if (empty($value)) {
            $this->attributes[$field] = null;
            return;
        }

        $dt = Carbon::parse($value);
        $dt->setTimezone('Asia/Shanghai');
        $this->attributes[$field] = $dt->format('Y-m-d H:i:s');
    }

    private function formatShanghaiDateTimeToUtcIso($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Carbon) {
            $dt = $value->copy();
            $dt->setTimezone('UTC');
            return $dt->format('Y-m-d\TH:i:s.u\Z');
        }

        $dt = Carbon::parse((string) $value, 'Asia/Shanghai');
        $dt->setTimezone('UTC');

        return $dt->format('Y-m-d\TH:i:s.u\Z');
    }
}

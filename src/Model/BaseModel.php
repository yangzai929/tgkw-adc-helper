<?php

namespace TgkwAdc\Model;

use Hyperf\ModelCache\Cacheable;
use Hyperf\DbConnection\Model\Model ;
use Hyperf\ModelCache\CacheableInterface;

class BaseModel extends Model implements CacheableInterface {
    use Cacheable;

    public function setCreatedAt($value): static
    {
        $this->attributes['created_at'] = time();
        return $this;
    }

    // 重写 updated_at 赋值逻辑：自动设为当前秒级时间戳
    public function setUpdatedAt($value): static
    {
        $this->attributes['updated_at'] = time();
        return $this;
    }

    public function setDeletedAt($value): static
    {
        $this->attributes['deleted_at'] = time();
        return $this;
    }
}
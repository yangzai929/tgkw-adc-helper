<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\JsonRpc\Public;

use Hyperf\RpcClient\AbstractServiceClient;
use TgkwAdc\JsonRpc\Hr\HrServiceInterface;

class PublicServiceConsumer extends AbstractServiceClient implements PublicServiceInterface
{
    public function handleFileUsed(string $object_key, int $is_used){
        return $this->__request(__FUNCTION__, compact('object_key', 'is_used'));
    }
}

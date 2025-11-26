<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Amqp\Producer;

use Exception;
use Hyperf\Amqp\Annotation\Producer;
use Hyperf\Amqp\Message\ProducerMessage;
use Hyperf\Amqp\Message\Type;

#[Producer(exchange: 'system.defaultFanOut', routingKey: 'system.defaultFanOut')]
class DefaultFanOutProducer extends ProducerMessage
{
    protected string|Type $type = Type::FANOUT; // 广播消息

    public function __construct(array $data)
    {
        // 没有消费类，直接报错
        if (empty($data['type'])) {
            throw new Exception('type参数 未传递');
        }
        if (empty($data['data'])) {
            throw new Exception('data参数 未传递');
        }

        $this->payload = $data;
    }
}

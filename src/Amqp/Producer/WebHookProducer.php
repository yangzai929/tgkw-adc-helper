<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Amqp\Producer;

use Hyperf\Amqp\Annotation\Producer;
use Hyperf\Amqp\Message\ProducerMessage;

/**
 * webhook消息生成器.
 */
#[Producer(exchange: 'log.webhook', routingKey: 'log.webhook')]
class WebHookProducer extends ProducerMessage
{
    public function __construct($data)
    {
        $this->payload = $data;
    }
}

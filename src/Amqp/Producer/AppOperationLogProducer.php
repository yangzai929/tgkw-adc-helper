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

#[Producer(exchange: 'log.appOperationLog', routingKey: 'log.appOperationLog')]
class AppOperationLogProducer extends ProducerMessage
{
    public function __construct(array $data)
    {
        $this->payload = $data;
    }
}

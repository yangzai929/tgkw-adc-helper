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

#[Producer(exchange: 'log.operationLog', routingKey: 'log.operationLog')]
class OperationLogProducer extends ProducerMessage
{
    public function __construct($data)
    {
        $this->payload = $data;
    }
}

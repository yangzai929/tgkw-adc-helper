<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Helper\Log;

use Hyperf\Context\Context;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class TraceIdProcessor implements ProcessorInterface
{
    public function __invoke(array|LogRecord $record)
    {
        $record['extra']['trace_id'] = Context::get('trace_id', '');
        return $record;
    }
}

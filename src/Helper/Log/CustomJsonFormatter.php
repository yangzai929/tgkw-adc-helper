<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Helper\Log;

use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;

class CustomJsonFormatter extends JsonFormatter
{
    public function format(LogRecord $record): string
    {
        // 重新排列字段顺序，将 datetime 放在第一位
        $formatted = [
            'datetime' => $record->datetime->format('Y-m-d H:i:s.u') . ' ',
            'channel' => $record->channel,
            'level' => $record->level->getName(),
            'message' => $record->message,
            'context' => $record->context,
        ];

        // 添加 extra 字段（如果存在）
        if (! empty($record->extra)) {
            $formatted['extra'] = $record->extra;
        }

        $trace_id = context_get('trace_id');
        if ($trace_id) {
            $formatted['trace_id'] = $trace_id;
        }

        return $this->toJson($formatted) . ($this->appendNewline ? "\n" : '');
    }
}

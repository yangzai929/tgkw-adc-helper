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
            'datetime' => $record->datetime->format($this->dateFormat),
            'message' => $record->message,
            'context' => $record->context,
            'level' => $record->level->value,
            'level_name' => $record->level->getName(),
            'channel' => $record->channel,
        ];

        // 添加 extra 字段（如果存在）
        if (! empty($record->extra)) {
            $formatted['extra'] = $record->extra;
        }

        return $this->toJson($formatted) . ($this->appendNewline ? "\n" : '');
    }
}

<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Exception;

use Hyperf\Server\Exception\ServerException;
use TgkwAdc\Annotation\EnumCodeInterface;

class BusinessException extends ServerException
{
    public function __construct($code, $message = '', array $i18nParam = [])
    {
        if ($code instanceof EnumCodeInterface) {
            $message = $i18nParam ? $code->genI18nMsg($i18nParam) : $code->getI18nMsg();
            parent::__construct($message, $code->getCode() ?? 400);
        } else {
            parent::__construct($message, $code);
        }
    }
}

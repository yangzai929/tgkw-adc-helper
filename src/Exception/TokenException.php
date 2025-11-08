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

class TokenException extends ServerException
{
    public function __construct(EnumCodeInterface $code, array $vars = [])
    {
        $message = $vars ? $code->genI18nMsg($vars) : $code->getI18nMsg();
        parent::__construct($message, $code->getCode() ?? 400);
    }
}

<?php

namespace TgkwAdc\Exception;

use Hyperf\Server\Exception\ServerException;

class BusinessException extends ServerException
{
    public function __construct(string $message, int $code = 400)
    {
        parent::__construct($message, $code);
    }
}
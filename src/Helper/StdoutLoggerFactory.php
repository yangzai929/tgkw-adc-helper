<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Helper;

use Psr\Container\ContainerInterface;
use TgkwAdc\Helper\Log\LogHelper;

class StdoutLoggerFactory
{
    public function __invoke(ContainerInterface $container)
    {
        return LogHelper::get('app');
    }
}

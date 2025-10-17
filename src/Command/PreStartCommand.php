<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Command;

use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;

#[Command]
class PreStartCommand extends HyperfCommand
{
    public function __construct()
    {
        parent::__construct('preStart');
    }

    public function handle()
    {
        $this->call('migrate');
    }
}

<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Resource\Captcha;

use TgkwAdc\Resource\BaseResource;

class CaptchaResource extends BaseResource
{
    public function toArray(): array
    {
        return [
            'captcha_type' => $this->captcha_type,
            'local_captcha' => $this->local_captcha,
            'aliyun_captcha' => $this->aliyun_captcha,
        ];
    }
}

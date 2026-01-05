<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Controller;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;
use TgkwAdc\Helper\ApiResponseHelper;
use TgkwAdc\Resource\Captcha\CaptchaResource;

use function TgkwAdc\HyperfCaptcha\captcha_create;

#[Controller]
class CaptchaController extends AbstractController
{
    #[GetMapping(path: 'captcha')]
    public function index()
    {
        //      $type = $this->configService->getByName('captcha_type');
        $configs = cfg('systemConfig');
        $configs = json_decode($configs, true);

        $data['captcha_type'] = $configs['captcha_type'];
        $data['local_captcha'] = [];
        $data['aliyun_captcha'] = [];
        if ($data['captcha_type'] == 'local_captcha') {
            # 创建图片验证码
            $data['local_captcha'] = captcha_create();
        } elseif ($data['captcha_type'] == 'aliyun_captcha') {
            $data['aliyun_captcha'] = [
                'aliyun_captcha_region' => $configs['aliyun_captcha_region'],
                'aliyun_captcha_scene_id' => $configs['aliyun_captcha_scene_id'],
                'aliyun_captcha_prefix_id' => $configs['aliyun_captcha_prefix_id'],
            ];
        }
        return ApiResponseHelper::success(CaptchaResource::make($data));
    }
}

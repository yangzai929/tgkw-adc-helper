<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Helper\Captcha;

use AlibabaCloud\Credentials\Credential;
use AlibabaCloud\Credentials\Credential\Config;
use AlibabaCloud\Dara\Models\RuntimeOptions;
use AlibabaCloud\SDK\Captcha\V20230305\Captcha;
use AlibabaCloud\SDK\Captcha\V20230305\Models\VerifyIntelligentCaptchaRequest;
use AlibabaCloud\Tea\Exception\TeaError;
use Darabonba\OpenApi\Models\Config as CaptchaConfig;
use Exception;
use RuntimeException;
use TgkwAdc\Constants\Code\CommonCode;
use TgkwAdc\Exception\BusinessException;
use TgkwAdc\Helper\Log\LogHelper;
use function TgkwAdc\HyperfCaptcha\captcha_verify;

class CaptchaVerifyService
{

    public static function captchaVerify($params)
    {
        $systemConfig = $systemConfig ?? self::getSystemConfig();
        $type = $systemConfig['captcha_type'];
        if ($type == 'local_captcha') {
            $res = captcha_verify($params['captcha_code'] ?? '', $params['captcha_verify_param'] ?? '');
            if (! $res) {
                throw new BusinessException(code: CommonCode::CAPTCHA_ERROR);
            }
        }
        if ($type == 'aliyun_captcha') {
            $res = self::main($params['captcha_verify_param'] ?? '');
            if (! $res) {
                throw new BusinessException(code: CommonCode::VERIFICATION_FAILED);
            }
        }
    }

    /**
     * 使用凭据初始化账号 Client.
     */
    public static function createClient(?array $systemConfig = null): Captcha
    {
        // 工程代码建议使用更安全的无 AK 方式，凭据配置方式请参见：https://help.aliyun.com/document_detail/311677.html。
        $systemConfig = $systemConfig ?? self::getSystemConfig();

        $requiredKeys = [
            'aliyun_captcha_access_secret_id',
            'aliyun_captcha_access_key_secret',
            'aliyun_captcha_region',
        ];

        foreach ($requiredKeys as $key) {
            if (empty($systemConfig[$key])) {
                LogHelper::error('aliyun captcha config missing', ['key' => $key], 'aliyun_captcha_verify');
                throw new RuntimeException(sprintf('Missing captcha config: %s', $key));
            }
        }

        $acKId = $systemConfig['aliyun_captcha_access_secret_id'];
        $acKSecret = $systemConfig['aliyun_captcha_access_key_secret'];
        $region = $systemConfig['aliyun_captcha_region'];

        $credConfig = new Config([
            'type' => 'access_key',
            'accessKeyId' => $acKId,
            'accessKeySecret' => $acKSecret,
        ]);
        $credential = new Credential($credConfig);
        $config = new CaptchaConfig([
            'credential' => $credential,
        ]);
        // Endpoint 请参考 https://api.aliyun.com/product/captcha

        $endpointMap = [
            'cn' => 'captcha.cn-shanghai.aliyuncs.com',
            'sgp' => 'captcha.ap-southeast-1.aliyuncs.com',
        ];

        if (! isset($endpointMap[$region])) {
            LogHelper::error('aliyun captcha region invalid', ['region' => $region], 'aliyun_captcha_verify');
            throw new RuntimeException('Invalid captcha region: ' . $region);
        }

        $config->endpoint = $endpointMap[$region];
        return new Captcha($config);
    }

    public static function verify(string $captchaVerifyParam): bool
    {
        $systemConfig = self::getSystemConfig();

        if (empty($systemConfig['aliyun_captcha_scene_id'])) {
            LogHelper::error('aliyun captcha scene id missing', [], 'aliyun_captcha_verify');
            return false;
        }

        $client = self::createClient($systemConfig);
        $verifyIntelligentCaptchaRequest = new VerifyIntelligentCaptchaRequest([
            'captchaVerifyParam' => $captchaVerifyParam,
            'sceneId' => $systemConfig['aliyun_captcha_scene_id'], // 从配置文件中取
        ]);
        try {
            $resp = $client->verifyIntelligentCaptchaWithOptions($verifyIntelligentCaptchaRequest, new RuntimeOptions([]));

            $respMap = $resp->body->toMap();
            LogHelper::info('aliyun captcha verify res', [
                'request' => ['captchaVerifyParam' => $captchaVerifyParam],
                'response' => $respMap,
            ], 'aliyun_captcha_verify');

            return isset($respMap['Code'], $respMap['Result']['VerifyResult'])
                && $respMap['Code'] === 'Success'
                && $respMap['Result']['VerifyResult'] === true;
        } catch (Exception $error) {
            if (! $error instanceof TeaError) {
                $error = new TeaError([], $error->getMessage(), $error->getCode(), $error);
            }
            LogHelper::error('aliyun captcha verify error', [$error->getMessage(), $error->getCode(), $error], 'aliyun_captcha_verify');
            return false;
        }
    }

    /**
     * 为保持兼容，保留原 main 方法.
     */
    public static function main(string $captchaVerifyParam): bool
    {
        return self::verify($captchaVerifyParam);
    }

    /**
     * 统一获取并解析系统配置.
     */
    private static function getSystemConfig(): array
    {
        $systemConfig = cfg('systemConfig');

        if (is_array($systemConfig)) {
            return $systemConfig;
        }

        if (is_string($systemConfig)) {
            $decoded = json_decode($systemConfig, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        LogHelper::error('systemConfig invalid', ['value' => $systemConfig], 'aliyun_captcha_verify');

        return [];
    }
}


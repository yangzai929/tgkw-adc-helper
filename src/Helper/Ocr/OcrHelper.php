<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Helper\Ocr;

use AlibabaCloud\Credentials\Credential;
use AlibabaCloud\Credentials\Credential\Config;
use AlibabaCloud\Dara\Models\RuntimeOptions;
use AlibabaCloud\SDK\Ocrapi\V20210707\Models\RecognizeAllTextRequest;
use AlibabaCloud\SDK\Ocrapi\V20210707\Models\RecognizeAllTextRequest\advancedConfig;
use AlibabaCloud\SDK\Ocrapi\V20210707\Models\RecognizeAllTextRequest\idCardConfig;
use AlibabaCloud\SDK\Ocrapi\V20210707\Models\RecognizeAllTextRequest\internationalBusinessLicenseConfig;
use AlibabaCloud\SDK\Ocrapi\V20210707\Models\RecognizeAllTextRequest\internationalIdCardConfig;
use AlibabaCloud\SDK\Ocrapi\V20210707\Models\RecognizeAllTextRequest\multiLanConfig;
use AlibabaCloud\SDK\Ocrapi\V20210707\Models\RecognizeAllTextRequest\tableConfig;
use AlibabaCloud\SDK\Ocrapi\V20210707\Models\RecognizeBusinessLicenseRequest;
use AlibabaCloud\SDK\Ocrapi\V20210707\Models\RecognizeGeneralStructureRequest;
use AlibabaCloud\SDK\Ocrapi\V20210707\Models\RecognizeIdcardRequest;
use AlibabaCloud\SDK\Ocrapi\V20210707\Models\RecognizeInternationalBusinessLicenseRequest;
use AlibabaCloud\SDK\Ocrapi\V20210707\Models\RecognizeInternationalIdcardRequest;
use AlibabaCloud\SDK\Ocrapi\V20210707\Models\RecognizePassportRequest;
use AlibabaCloud\SDK\Ocrapi\V20210707\Ocrapi;
use AlibabaCloud\Tea\Exception\TeaError;
use Darabonba\OpenApi\Models\Config as OpenApiConfig;
use Exception;
use GuzzleHttp\Psr7\Utils;
use TgkwAdc\Constants\Code\CommonCode;
use TgkwAdc\Exception\BusinessException;
use TgkwAdc\Helper\Log\LogHelper;

/**
 * 阿里云 OCR 静态工具类.
 *
 * 凭据从 cfg('systemConfig') 读取，图片入参支持公网 URL、本地路径、Base64/二进制。
 */
class OcrHelper
{
    private const LOG_CHANNEL = 'aliyun_ocr';

    private const DEFAULT_ENDPOINT = 'ocr-api.cn-hangzhou.aliyuncs.com';

    /**
     * COCR 统一识别.
     *
     * @param string $image 公网 URL / 本地路径 / Base64 或二进制
     * @param string $type 图片类型，默认 Advanced
     * @param array $options 可选 SDK 配置（如 outputStamp、advancedConfig 等）
     */
    public static function recognizeAllText(string $image, string $type = 'Advanced', array $options = []): array
    {
        if ($type === '') {
            throw new BusinessException(CommonCode::PARAM_ERROR);
        }

        $request = new RecognizeAllTextRequest([
            'type' => $type,
        ]);
        self::applyAllTextOptions($request, $options);
        self::applyImage($request, $image);

        return self::invoke('recognizeAllText', static function () use ($request) {
            $resp = self::createClient()->recognizeAllTextWithOptions($request, new RuntimeOptions([]));

            return $resp->body?->toMap() ?? [];
        });
    }

    /**
     * 通用票证抽取.
     *
     * @param string[] $keys 要抽取的字段名
     */
    public static function recognizeGeneralStructure(string $image, array $keys = []): array
    {
        $payload = [];
        if ($keys !== []) {
            $payload['keys'] = array_values($keys);
        }
        $request = new RecognizeGeneralStructureRequest($payload);
        self::applyImage($request, $image);

        return self::invoke('recognizeGeneralStructure', static function () use ($request) {
            $resp = self::createClient()->recognizeGeneralStructureWithOptions($request, new RuntimeOptions([]));

            return $resp->body?->toMap() ?? [];
        });
    }

    /**
     * 身份证识别.
     *
     * @param array $options 可选：llmRec / outputFigure / outputQualityInfo
     */
    public static function recognizeIdcard(string $image, array $options = []): array
    {
        $payload = [];
        foreach (['llmRec', 'outputFigure', 'outputQualityInfo'] as $key) {
            if (array_key_exists($key, $options)) {
                $payload[$key] = $options[$key];
            }
        }
        $request = new RecognizeIdcardRequest($payload);
        self::applyImage($request, $image);

        return self::invoke('recognizeIdcard', static function () use ($request) {
            $resp = self::createClient()->recognizeIdcardWithOptions($request, new RuntimeOptions([]));

            return $resp->body?->toMap() ?? [];
        });
    }

    /**
     * 国际护照识别.
     */
    public static function recognizePassport(string $image): array
    {
        $request = new RecognizePassportRequest([]);
        self::applyImage($request, $image);

        return self::invoke('recognizePassport', static function () use ($request) {
            $resp = self::createClient()->recognizePassportWithOptions($request, new RuntimeOptions([]));

            return $resp->body?->toMap() ?? [];
        });
    }

    /**
     * 国际身份证识别.
     *
     * @param string $country 国家码，如 USA
     */
    public static function recognizeInternationalIdcard(string $image, string $country): array
    {
        if ($country === '') {
            throw new BusinessException(CommonCode::PARAM_ERROR);
        }

        $request = new RecognizeInternationalIdcardRequest([
            'country' => $country,
        ]);
        self::applyImage($request, $image);

        return self::invoke('recognizeInternationalIdcard', static function () use ($request) {
            $resp = self::createClient()->recognizeInternationalIdcardWithOptions($request, new RuntimeOptions([]));

            return $resp->body?->toMap() ?? [];
        });
    }

    /**
     * 营业执照识别.
     */
    public static function recognizeBusinessLicense(string $image): array
    {
        $request = new RecognizeBusinessLicenseRequest([]);
        self::applyImage($request, $image);

        return self::invoke('recognizeBusinessLicense', static function () use ($request) {
            $resp = self::createClient()->recognizeBusinessLicenseWithOptions($request, new RuntimeOptions([]));

            return $resp->body?->toMap() ?? [];
        });
    }

    /**
     * 国际企业执照识别.
     *
     * @param string $country 国家码，如 USA
     */
    public static function recognizeInternationalBusinessLicense(string $image, string $country): array
    {
        if ($country === '') {
            throw new BusinessException(CommonCode::PARAM_ERROR);
        }

        $request = new RecognizeInternationalBusinessLicenseRequest([
            'country' => $country,
        ]);
        self::applyImage($request, $image);

        return self::invoke('recognizeInternationalBusinessLicense', static function () use ($request) {
            $resp = self::createClient()->recognizeInternationalBusinessLicenseWithOptions($request, new RuntimeOptions([]));

            return $resp->body?->toMap() ?? [];
        });
    }

    /**
     * 使用凭据初始化 OCR Client.
     */
    public static function createClient(?array $systemConfig = null): Ocrapi
    {
        $systemConfig = $systemConfig ?? self::getSystemConfig();

        $requiredKeys = [
            'aliyun_ocr_access_key_id',
            'aliyun_ocr_access_key_secret',
        ];

        foreach ($requiredKeys as $key) {
            if (empty($systemConfig[$key])) {
                LogHelper::error('aliyun ocr config missing', ['key' => $key], self::LOG_CHANNEL);
                throw new BusinessException(CommonCode::PARAM_ERROR);
            }
        }

        $credConfig = new Config([
            'type' => 'access_key',
            'accessKeyId' => $systemConfig['aliyun_ocr_access_key_id'],
            'accessKeySecret' => $systemConfig['aliyun_ocr_access_key_secret'],
        ]);
        $credential = new Credential($credConfig);
        $config = new OpenApiConfig([
            'credential' => $credential,
        ]);
        $config->endpoint = ! empty($systemConfig['aliyun_ocr_endpoint'])
            ? (string) $systemConfig['aliyun_ocr_endpoint']
            : self::DEFAULT_ENDPOINT;

        return new Ocrapi($config);
    }

    /**
     * @param object{url?: null|string, body?: mixed} $request
     */
    private static function applyImage(object $request, string $image): void
    {
        $resolved = self::resolveImage($image);
        if (isset($resolved['url'])) {
            $request->url = $resolved['url'];
            $request->body = null;
            return;
        }

        $request->url = null;
        $request->body = $resolved['body'];
    }

    /**
     * @return array{url?: string, body?: \Psr\Http\Message\StreamInterface}
     */
    private static function resolveImage(string $image): array
    {
        if ($image === '') {
            LogHelper::error('aliyun ocr image empty', [], self::LOG_CHANNEL);
            throw new BusinessException(CommonCode::PARAM_ERROR);
        }

        if (preg_match('#^https?://#i', $image) === 1) {
            return ['url' => $image];
        }

        if (is_file($image) && is_readable($image)) {
            $resource = fopen($image, 'rb');
            if ($resource === false) {
                LogHelper::error('aliyun ocr image file open failed', ['path' => $image], self::LOG_CHANNEL);
                throw new BusinessException(CommonCode::PARAM_ERROR);
            }

            return ['body' => Utils::streamFor($resource)];
        }

        $binary = $image;
        if (preg_match('#^data:image/[^;]+;base64,#i', $image, $matches) === 1) {
            $binary = substr($image, strlen($matches[0]));
        }

        $normalized = preg_replace('/\s+/', '', $binary) ?? $binary;
        $decoded = base64_decode($normalized, true);
        if ($decoded !== false && $decoded !== '' && base64_encode($decoded) === $normalized) {
            return ['body' => Utils::streamFor($decoded)];
        }

        return ['body' => Utils::streamFor($image)];
    }

    private static function applyAllTextOptions(RecognizeAllTextRequest $request, array $options): void
    {
        $scalarKeys = [
            'outputBarCode',
            'outputCoordinate',
            'outputFigure',
            'outputKVExcel',
            'outputOricoord',
            'outputQrcode',
            'outputStamp',
            'pageNo',
        ];
        foreach ($scalarKeys as $key) {
            if (array_key_exists($key, $options)) {
                $request->{$key} = $options[$key];
            }
        }

        $configMap = [
            'advancedConfig' => advancedConfig::class,
            'idCardConfig' => idCardConfig::class,
            'internationalBusinessLicenseConfig' => internationalBusinessLicenseConfig::class,
            'internationalIdCardConfig' => internationalIdCardConfig::class,
            'multiLanConfig' => multiLanConfig::class,
            'tableConfig' => tableConfig::class,
        ];
        foreach ($configMap as $key => $class) {
            if (! array_key_exists($key, $options) || $options[$key] === null) {
                continue;
            }
            $value = $options[$key];
            if (is_array($value)) {
                $request->{$key} = $class::fromMap(self::toPascalKeyMap($value));
            } elseif ($value instanceof $class) {
                $request->{$key} = $value;
            }
        }
    }

    /**
     * @param array<string, mixed> $map
     * @return array<string, mixed>
     */
    private static function toPascalKeyMap(array $map): array
    {
        $result = [];
        foreach ($map as $key => $value) {
            $pascal = is_string($key) && $key !== '' && $key[0] === strtolower($key[0])
                ? ucfirst($key)
                : (string) $key;
            $result[$pascal] = is_array($value) ? self::toPascalKeyMap($value) : $value;
        }

        return $result;
    }

    /**
     * @param callable(): array $callback
     */
    private static function invoke(string $action, callable $callback): array
    {
        try {
            $result = $callback();
            LogHelper::info('aliyun ocr success', ['action' => $action], self::LOG_CHANNEL);

            return $result;
        } catch (BusinessException $e) {
            throw $e;
        } catch (Exception $error) {
            if (! $error instanceof TeaError) {
                $error = new TeaError([], $error->getMessage(), $error->getCode(), $error);
            }
            LogHelper::error('aliyun ocr error', [
                'action' => $action,
                'message' => $error->getMessage(),
                'code' => $error->getCode(),
                'error' => $error,
            ], self::LOG_CHANNEL);

            throw new BusinessException(CommonCode::OCR_FAILED);
        }
    }

    /**
     * 统一获取并解析系统配置.
     */
    private static function getSystemConfig(): array
    {
        $systemConfig = function_exists('cfg') ? cfg('systemConfig') : null;

        if (is_array($systemConfig)) {
            return $systemConfig;
        }

        if (is_string($systemConfig)) {
            $decoded = json_decode($systemConfig, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        LogHelper::error('systemConfig invalid', ['value' => $systemConfig], self::LOG_CHANNEL);

        return [];
    }
}

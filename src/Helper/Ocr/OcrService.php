<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Helper\Ocr;

use TgkwAdc\Constants\Code\CommonCode;
use TgkwAdc\Exception\BusinessException;

/**
 * 阿里云 OCR 业务服务（供各微服务直接注入 / make 使用）.
 *
 * 用法：
 *   make(OcrService::class)->recognize($params);
 *   // 或
 *   (new OcrService())->recognize($params);
 */
class OcrService
{
    /**
     * 按识别类型调用阿里云 OCR.
     *
     * 参数说明：
     * - recognize_type (必填) 识别类型，可选值：
     *   - all_text                       COCR 统一识别
     *   - general_structure              通用票证抽取
     *   - idcard                         身份证
     *   - passport                       国际护照
     *   - international_idcard           国际身份证
     *   - business_license               营业执照
     *   - international_business_license 国际企业执照
     * - image (必填) 图片内容：公网 URL / 本地路径 / Base64（可带 data:image/...;base64, 前缀）
     * - type (选填) 仅 all_text 有效，COCR 图片类型，默认 Advanced；也可传 General、IdCard、Table 等
     * - options (选填) 仅 all_text 有效，COCR 额外 SDK 配置，如 outputStamp、advancedConfig
     * - keys (选填) 仅 general_structure 有效，要抽取的字段名字符串数组，如 ["发票号码","金额"]
     * - country (条件必填) international_idcard / international_business_license 时必填，国家码如 USA、CHN
     * - llm_rec (选填) 仅 idcard，是否启用大模型增强，true/false
     * - output_figure (选填) 仅 idcard，是否输出图案信息，true/false
     * - output_quality_info (选填) 仅 idcard，是否输出质量检测，true/false
     *
     * @param array $params 识别参数
     */
    public function recognize(array $params): array
    {
        $image = (string) ($params['image'] ?? '');
        $recognizeType = (string) ($params['recognize_type'] ?? '');

        return match ($recognizeType) {
            'all_text' => OcrHelper::recognizeAllText(
                $image,
                (string) ($params['type'] ?? 'Advanced'),
                (array) ($params['options'] ?? [])
            ),
            'general_structure' => OcrHelper::recognizeGeneralStructure(
                $image,
                array_values((array) ($params['keys'] ?? []))
            ),
            'idcard' => OcrHelper::recognizeIdcard($image, $this->idcardOptions($params)),
            'passport' => OcrHelper::recognizePassport($image),
            'international_idcard' => OcrHelper::recognizeInternationalIdcard(
                $image,
                (string) ($params['country'] ?? '')
            ),
            'business_license' => OcrHelper::recognizeBusinessLicense($image),
            'international_business_license' => OcrHelper::recognizeInternationalBusinessLicense(
                $image,
                (string) ($params['country'] ?? '')
            ),
            default => throw new BusinessException(CommonCode::PARAM_ERROR),
        };
    }

    private function idcardOptions(array $params): array
    {
        $options = [];
        if (array_key_exists('llm_rec', $params)) {
            $options['llmRec'] = (bool) $params['llm_rec'];
        }
        if (array_key_exists('output_figure', $params)) {
            $options['outputFigure'] = (bool) $params['output_figure'];
        }
        if (array_key_exists('output_quality_info', $params)) {
            $options['outputQualityInfo'] = (bool) $params['output_quality_info'];
        }

        return $options;
    }
}

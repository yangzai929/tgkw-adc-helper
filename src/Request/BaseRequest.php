<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Request;

use Hyperf\Context\ApplicationContext;
use Hyperf\Context\Context;
use Hyperf\Contract\TranslatorInterface;
use Hyperf\Contract\ValidatorInterface;
use Hyperf\Validation\Request\FormRequest;
use TgkwAdc\Constants\LocaleConstants;

abstract class BaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 获取自定义验证消息.
     * 支持类特定的自定义消息翻译：
     * 1. validation.custom.{ClassName}.{field}.{rule} - 类特定翻译
     * 2. validation.custom.{field}.{rule} - 通用翻译
     * 3. validation.{rule} - 默认规则消息（由 Hyperf 验证器自动处理）
     */
    public function messages(): array
    {
        $translator = ApplicationContext::getContainer()->get(TranslatorInterface::class);
        $locale = $translator->getLocale();
        $className = get_class($this);
        $messages = [];

        // 获取当前场景的规则
        $rules = $this->getRules();

        foreach ($rules as $field => $rule) {
            $ruleArray = is_string($rule) ? explode('|', $rule) : $rule;

            foreach ($ruleArray as $singleRule) {
                $ruleName = explode(':', $singleRule)[0];

                // 1. 优先尝试获取类特定的自定义消息
                $classKey = "validation.custom.{$className}.{$field}.{$ruleName}";
                $classMessage = $translator->trans($classKey, [], $locale);

                if ($classMessage !== $classKey) {
                    $messages["{$field}.{$ruleName}"] = $classMessage;
                    continue;
                }

                // 2. 尝试获取通用自定义消息
                $generalKey = "validation.custom.{$field}.{$ruleName}";
                $generalMessage = $translator->trans($generalKey, [], $locale);

                if ($generalMessage !== $generalKey) {
                    $messages["{$field}.{$ruleName}"] = $generalMessage;
                }
                // 3. 如果都找不到，让 Hyperf 验证器自动处理（从 validation.{rule} 获取）
            }
        }

        return $messages;
    }

    /**
     * 获取自定义字段名称.
     * 支持类特定的字段名称翻译：
     * 1. validation.attributes.{ClassName}.{field} - 类特定翻译
     * 2. validation.attributes.{field} - 通用翻译
     */
    public function attributes(): array
    {
        $translator = ApplicationContext::getContainer()->get(TranslatorInterface::class);
        $locale = $translator->getLocale();
        $className = get_class($this);
        $attributes = [];

        // 获取当前场景的规则字段
        $rules = $this->getRules();

        foreach (array_keys($rules) as $field) {
            // 1. 优先尝试获取类特定的字段名称
            $classKey = "validation.attributes.{$className}.{$field}";
            $classAttribute = $translator->trans($classKey, [], $locale);

            if ($classAttribute !== $classKey) {
                $attributes[$field] = $classAttribute;
                continue;
            }

            // 2. 尝试获取通用字段名称
            $generalKey = "validation.attributes.{$field}";
            $generalAttribute = $translator->trans($generalKey, [], $locale);

            if ($generalAttribute !== $generalKey) {
                $attributes[$field] = $generalAttribute;
            }
        }

        return $attributes;
    }


}

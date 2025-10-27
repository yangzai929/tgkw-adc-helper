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
     * 获取国际化验证消息.
     */
    public function messages(): array
    {
        $locale = $this->getLocale();
        $translator = $this->getTranslator();
        $messages = [];

        foreach ($this->rules() as $field => $rule) {
            $ruleArray = is_string($rule) ? explode('|', $rule) : $rule;
            foreach ($ruleArray as $singleRule) {
                $ruleName = explode(':', $singleRule)[0];

                // 1. 优先尝试获取 custom 格式的验证消息
                $customKey = "validation.custom.{$field}.{$ruleName}";
                $customMessage = $translator->trans($customKey, [], $locale);

                if ($customMessage !== $customKey) {
                    $messages["{$field}.{$ruleName}"] = $customMessage;
                    continue;
                }

                // 2. 尝试获取字段特定的验证消息
                $fieldRuleKey = "validation.{$field}.{$ruleName}";
                $fieldRuleMessage = $translator->trans($fieldRuleKey, [], $locale);

                if ($fieldRuleMessage !== $fieldRuleKey) {
                    $messages["{$field}.{$ruleName}"] = $fieldRuleMessage;
                    continue;
                }

                // 3. 如果字段特定消息不存在，使用通用验证消息
                $generalKey = "validation.{$ruleName}";
                $generalMessage = $translator->trans($generalKey, [], $locale);

                if ($generalMessage !== $generalKey) {
                    $messages["{$field}.{$ruleName}"] = $generalMessage;
                }
            }
        }

        return $messages;
    }

    /**
     * 获取国际化字段名称.
     */
    public function attributes(): array
    {
        $locale = $this->getLocale();
        $translator = $this->getTranslator();
        $attributes = [];

        foreach (array_keys($this->rules()) as $field) {
            $key = "validation.attributes.{$field}";
            $attribute = $translator->trans($key, [], $locale);
            if ($attribute !== $key) {
                $attributes[$field] = $attribute;
            }
        }

        return $attributes;
    }

    /**
     * 配置验证器.
     * @param mixed $validator
     */
    public function withValidator($validator)
    {
        $locale = $this->getLocale();
        $validator->getTranslator()->setLocale($locale);

        // 设置字段名称翻译
        $attributesTranslation = $this->attributes();
        if (! empty($attributesTranslation) && method_exists($validator, 'setAttributeNames')) {
            $validator->setAttributeNames($attributesTranslation);
        }

        // 设置自定义验证消息
        $customMessages = $this->messages();
        if (! empty($customMessages) && method_exists($validator, 'setCustomMessages')) {
            $validator->setCustomMessages($customMessages);
        }
    }

    /**
     * 获取当前语言环境.
     */
    protected function getLocale(): string
    {
        // 优先从 Context 获取（中间件可能已设置）
        if ($locale = Context::get('locale')) {
            return $locale;
        }

        // 从请求属性获取（中间件设置）
        if ($locale = $this->getAttribute('locale')) {
            return $locale;
        }

        // 从输入参数获取
        $locale = $this->input('lang', LocaleConstants::getDefaultLocale());

        return LocaleConstants::isSupported($locale) ? $locale : LocaleConstants::getDefaultLocale();
    }

    /**
     * 验证前准备数据.
     */
    protected function prepareForValidation()
    {
        $locale = $this->getLocale();
        $translator = $this->getTranslator();

        // 设置翻译器语言环境
        $translator->setLocale($locale);

        // 保存到 Context
        Context::set('locale', $locale);
        Context::set('debug_locale', $locale);
    }

    /**
     * 获取翻译器实例.
     */
    protected function getTranslator(): TranslatorInterface
    {
        return ApplicationContext::getContainer()->get(TranslatorInterface::class);
    }

    /**
     * 重写验证器生成逻辑
     * 确保 attributes() 和 withValidator() 始终被调用.
     */
    protected function getValidatorInstance(): ValidatorInterface
    {
        $validator = parent::getValidatorInstance();

        // 调用字段翻译
        $attributes = $this->attributes();
        if (! empty($attributes)) {
            $validator->setAttributeNames($attributes);
        }

        // 调用自定义消息
        $customMessages = $this->messages();
        if (! empty($customMessages) && method_exists($validator, 'setCustomMessages')) {
            $validator->setCustomMessages($customMessages);
        }

        // 调用验证器额外配置
        $this->withValidator($validator);

        return $validator;
    }
}

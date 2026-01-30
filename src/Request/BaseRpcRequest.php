<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Request;

use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Validation\ValidationException;
use TgkwAdc\Helper\LocaleHelper;

abstract class BaseRpcRequest
{
    /**
     * 场景化规则，保持与 HTTP Request 一致，便于复用.
     */
    protected array $sceneRules = [];

    /**
     * 验证错误消息.
     */
    protected array $messages = [];

    public function __construct(private readonly ValidatorFactoryInterface $validatorFactory)
    {
    }

    public function validate(array $input, string $scene): array
    {
        $sceneRules = $this->sceneRules[$scene] ?? [];

        // 如果子类定义了 rules() 方法，合并规则
        $baseRules = method_exists($this, 'rules') ? $this->rules($scene) : [];

        // 过滤 sceneRules：移除只有字段名没有规则的项
        $filteredSceneRules = [];
        foreach ($sceneRules as $key => $value) {
            // 如果是数字键，说明是只有字段名没有规则，跳过
            if (is_int($key)) {
                continue;
            }
            $filteredSceneRules[$key] = $value;
        }

        // 合并规则：rules() 中的规则会覆盖 sceneRules 中同名字段的规则
        $rules = array_merge($filteredSceneRules, $baseRules);

        // 为自定义规则设置数据上下文
        foreach ($rules as $field => $ruleSet) {
            if (is_array($ruleSet)) {
                foreach ($ruleSet as $rule) {
                    if (is_object($rule) && method_exists($rule, 'setData')) {
                        $rule->setData($input);
                    }
                }
            }
        }

        $this->validatorFactory->getTranslator()->setLocale(LocaleHelper::getCurrentLocale());
        $validator = $this->validatorFactory->make($input, $rules, $this->messages);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}

<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Annotation;

interface EnumI18nInterface
{
    /**
     * 获取所有文本内容.
     */
    public function getTxtArr(): ?array;

    /**
     * 获取文本内容.
     */
    public function getTxt(): ?string;

    /**
     * 获取集合编码.
     */
    public function getI18nGroupCode(): ?int;

    /**
     * 获取i18n的内容.
     */
    public function getI18nTxt(?string $key = null): array|string|null;

    /**
     * 获取i18n的组装内容，用于返回.
     */
    public function genI18nTxt(array $i18nParam = []): array|string;
}

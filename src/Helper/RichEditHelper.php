<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Helper;

use Hyperf\Di\Annotation\Inject;
use TgkwAdc\Constants\GlobalConstants;

class RichEditHelper
{
    private const OBJECK_KEY_PATTERN = '#(' . GlobalConstants::OBJECK_KEY_PREFIX . '/[^?"\'&]+)#';

    #[Inject]
    protected FileSystemHelper $fileSystemHelper;

    /**
     * 清洗 富文本 HTML 内容，将 对象存储 临时 URL 替换为新的临时 URL.
     */
    public function handleContents(string $content): string
    {
        $content = $this->refreshImgSrc($content);
        $content = $this->refreshVideoSrc($content);
        return $this->refreshDataHref($content);
    }

    /**
     * 刷新 <img> 标签的 src.
     */
    protected function refreshImgSrc(string $content): string
    {
        return $this->replaceAttrUrl($content, 'img', 'src');
    }

    /**
     * 刷新 <source> 标签的 src（视频）.
     */
    protected function refreshVideoSrc(string $content): string
    {
        return $this->replaceAttrUrl($content, 'source', 'src');
    }

    /**
     * 刷新 data-href 属性（图片原始链接）.
     */
    protected function refreshDataHref(string $content): string
    {
        return $this->replaceAttrUrl($content, 'img', 'data-href');
    }

    /**
     * 替换指定标签指定属性中的 OSS URL.
     */
    protected function replaceAttrUrl(string $content, string $tag, string $attr): string
    {
        $pattern = '#(<' . $tag . '\b[^>]*\b' . preg_quote($attr, '#') . '=")([^"]*)(")#i';

        return preg_replace_callback($pattern, function (array $matches) {
            $url = $matches[2];
            $objectKey = $this->extractObjectKey($url);
            if ($objectKey === null) {
                return $matches[0];
            }
            $newUrl = $this->fileSystemHelper->genFileTempUrl($objectKey);
            return $matches[1] . $newUrl . $matches[3];
        }, $content) ?? $content;
    }

    /**
     * 从  URL 中提取 object_key (tgkwfile/... 到 ? 之前).
     */
    protected function extractObjectKey(string $url): ?string
    {
        if (preg_match(self::OBJECK_KEY_PATTERN, $url, $matches)) {
            return $matches[1];
        }
        return null;
    }
}

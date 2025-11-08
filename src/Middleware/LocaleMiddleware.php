<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Middleware;

use Hyperf\Context\ApplicationContext;
use Hyperf\Context\Context;
use Hyperf\Contract\TranslatorInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TgkwAdc\Constants\LocaleConstants;

class LocaleMiddleware implements MiddlewareInterface
{
    private ?TranslatorInterface $translator = null;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 获取语言设置
        $locale = $this->detectLocale($request);

        // 懒加载翻译器实例（单例模式，性能更好）
        if ($this->translator === null) {
            $container = ApplicationContext::getContainer();
            $this->translator = $container->get(TranslatorInterface::class);
        }

        // 设置到翻译器
        $this->translator->setLocale($locale);

        // 将语言设置存储到上下文中，供其他地方使用
        Context::set('locale', $locale);

        // 将语言设置添加到请求属性中
        $request = $request->withAttribute('locale', $locale);

        return $handler->handle($request);
    }

    /**
     * 获取支持的语言列表.
     */
    public function getSupportedLocales(): array
    {
        return LocaleConstants::getSupportedLocaleCodes();
    }

    /**
     * 获取当前语言
     */
    public function getCurrentLocale(): string
    {
        return Context::get('locale', LocaleConstants::getDefaultLocale());
    }

    /**
     * 检测当前请求的语言
     */
    protected function detectLocale(ServerRequestInterface $request): string
    {
        // 1. 优先从请求参数获取
        $locale = $request->getQueryParams()['lang'] ?? null;
        if ($locale && $this->isValidLocale($locale)) {
            return LocaleConstants::SUPPORTED_LOCALES[$locale];
        }

        // 2. 从请求体获取
        $body = $request->getParsedBody();
        if (is_array($body) && isset($body['lang'])) {
            $locale = $body['lang'];
            if ($this->isValidLocale($locale)) {
                return LocaleConstants::SUPPORTED_LOCALES[$locale];
            }
        }

        // 3. 从请求头获取
        $acceptLanguage = $request->getHeaderLine('Accept-Language');
        if ($acceptLanguage) {
            $locale = $this->parseAcceptLanguage($acceptLanguage);
            if ($locale && $this->isValidLocale($locale)) {
                return LocaleConstants::SUPPORTED_LOCALES[$locale];
            }
        }

        // 4. 从自定义语言头获取
        $customLanguage = $request->getHeaderLine('Language');
        if ($customLanguage && $this->isValidLocale($customLanguage)) {
            return LocaleConstants::SUPPORTED_LOCALES[$customLanguage];
        }

        // 5. 从Cookie获取
        $cookies = $request->getCookieParams();
        $locale = $cookies['locale'] ?? null;
        if ($locale && $this->isValidLocale($locale)) {
            return LocaleConstants::SUPPORTED_LOCALES[$locale];
        }

        // 6. 返回默认语言
        return LocaleConstants::getDefaultLocale();
    }

    /**
     * 解析Accept-Language头.
     */
    protected function parseAcceptLanguage(string $acceptLanguage): ?string
    {
        $languages = explode(',', $acceptLanguage);

        foreach ($languages as $language) {
            $language = trim(explode(';', $language)[0]);

            // 处理完整语言代码 (如 zh-CN, en-US)
            if (strpos($language, '-') !== false) {
                $parts = explode('-', $language);
                $locale = $parts[0] . '_' . strtoupper($parts[1]);
                if ($this->isValidLocale($locale)) {
                    return $locale;
                }
            }

            // 处理简单语言代码 (如 zh, en)
            if ($this->isValidLocale($language)) {
                return $language;
            }
        }

        return null;
    }

    /**
     * 验证语言代码是否有效.
     */
    protected function isValidLocale(string $locale): bool
    {
        return LocaleConstants::isSupported($locale);
    }
}

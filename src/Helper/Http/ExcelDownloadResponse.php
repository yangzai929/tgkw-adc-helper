<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Helper\Http;

use Hyperf\Context\ApplicationContext;
use Hyperf\HttpMessage\Server\Response;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Psr\Http\Message\ResponseInterface;

final class ExcelDownloadResponse
{
    private const CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public static function create(
        string $content,
        string $filename,
        ?ResponseInterface $response = null
    ): ResponseInterface {
        $response ??= ApplicationContext::hasContainer()
            ? container_get(ResponseInterface::class)
            : new Response();

        return self::apply($response->withBody(new SwooleStream($content)), $filename);
    }

    public static function apply(ResponseInterface $response, string $filename): ResponseInterface
    {
        $filename = self::normalizeFilename($filename);
        $encodedFilename = rawurlencode($filename);
        $fallbackFilename = self::fallbackFilename($filename);

        return $response
            ->withHeader('Server', 'TgkwAdc')
            ->withHeader('Access-Control-Expose-Headers', 'Content-Disposition')
            ->withHeader('Content-Description', 'File Transfer')
            ->withHeader('Content-Type', self::CONTENT_TYPE)
            ->withHeader(
                'Content-Disposition',
                sprintf("attachment; filename=\"%s\"; filename*=UTF-8''%s", $fallbackFilename, $encodedFilename)
            )
            ->withHeader('Content-Transfer-Encoding', 'binary')
            ->withHeader('Pragma', 'public');
    }

    private static function normalizeFilename(string $filename): string
    {
        $filename = basename(str_replace('\\', '/', trim($filename)));
        $filename = preg_replace('/[\x00-\x1F\x7F]/u', '', $filename) ?: 'download';

        return str_ends_with(strtolower($filename), '.xlsx') ? $filename : $filename . '.xlsx';
    }

    private static function fallbackFilename(string $filename): string
    {
        $stem = pathinfo($filename, PATHINFO_FILENAME);
        $stem = preg_replace('/[^A-Za-z0-9._-]+/', '-', $stem) ?: '';
        $stem = trim($stem, '-._');

        return ($stem !== '' ? $stem : 'download') . '.xlsx';
    }
}

<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace HyperfTest\Cases;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use TgkwAdc\Utils\CompanyInfo\JisuApi;

/**
 * @internal
 * @coversNothing
 */
class JisuApiTest extends AbstractTestCase
{
    public function testClientDisablesHttpErrorsSoNotFoundJsonIsNotThrown(): void
    {
        $api = new JisuApi('test-app-code');

        $client = $this->readClient($api);
        self::assertFalse($client->getConfig('http_errors'), '必须关闭 Guzzle http_errors，否则 404 查无数据会被当成传输异常抛出');
    }

    public function testSearchTreatsHttp404WithStatusAsBusinessResponse(): void
    {
        $api = new JisuApi('test-app-code');
        $this->injectClient($api, new Client([
            'handler' => HandlerStack::create(new MockHandler([
                new Response(
                    404,
                    ['Content-Type' => 'application/json'],
                    '{"status":"210","msg":"没有信息","result":""}'
                ),
            ])),
            'http_errors' => false,
        ]));

        $result = $api->search('天宫开五2');

        self::assertSame('210', (string) $result['status']);
        self::assertSame('没有信息', $result['msg']);
    }

    private function readClient(JisuApi $api): Client
    {
        $ref = new \ReflectionProperty(JisuApi::class, 'client');
        $ref->setAccessible(true);

        return $ref->getValue($api);
    }

    private function injectClient(JisuApi $api, Client $client): void
    {
        $ref = new \ReflectionProperty(JisuApi::class, 'client');
        $ref->setAccessible(true);
        $ref->setValue($api, $client);
    }
}
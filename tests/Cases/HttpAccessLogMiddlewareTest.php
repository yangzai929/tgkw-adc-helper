<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace HyperfTest\Cases;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use Hyperf\Context\Context;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use TgkwAdc\Constants\GlobalConstants;
use TgkwAdc\Middleware\HttpAccessLogMiddleware;
use Throwable;

/**
 * @internal
 * @covers \TgkwAdc\Middleware\HttpAccessLogMiddleware
 */
final class HttpAccessLogMiddlewareTest extends AbstractTestCase
{
    protected function tearDown(): void
    {
        foreach ([
            'trace_id',
            'app_id',
            GlobalConstants::ORG_USER_CONTEXT,
            GlobalConstants::SYS_ADMIN_CONTEXT,
            GlobalConstants::BASE_USER_CONTEXT,
        ] as $key) {
            Context::destroy($key);
        }

        putenv('APP_NAME');
        parent::tearDown();
    }

    public function testPublishesLightweightMetadataWithoutConsumingResponseBody(): void
    {
        putenv('APP_NAME=user');
        Context::set('trace_id', 'trace-123');
        Context::set('app_id', '1');
        Context::set(GlobalConstants::ORG_USER_CONTEXT, [
            'id' => 30001,
            'real_name' => '张三',
            'current_tenant_id' => 20001,
            'mobile' => '13800000000',
        ]);

        $middleware = new TestableHttpAccessLogMiddleware();
        $request = $this->request('PUT', '/v1/roles/10001', [
            GlobalConstants::ORG_TOKEN_KEY => 'secret-token',
            'X-Request-Id' => 'request-123',
            'User-Agent' => 'PHPUnit Browser',
            'X-Forwarded-For' => '203.0.113.10',
        ]);
        $response = new Response(200, ['Content-Length' => '7'], 'payload');

        $returned = $middleware->process($request, new CallbackRequestHandler(
            static fn (): ResponseInterface => $response
        ));

        self::assertSame($response, $returned);
        self::assertSame(0, $returned->getBody()->tell(), 'Middleware must not consume the response stream.');
        self::assertCount(1, $middleware->published);

        $payload = $middleware->published[0];
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $payload['event_id']);
        self::assertSame('user', $payload['service_name']);
        self::assertSame('1', $payload['app_id']);
        self::assertSame(20001, $payload['tenant_id']);
        self::assertSame(30001, $payload['user_id']);
        self::assertSame('张三', $payload['username']);
        self::assertSame('org', $payload['origin']);
        self::assertSame('PUT', $payload['method']);
        self::assertSame('/v1/roles/10001', $payload['router']);
        self::assertSame(200, $payload['response_code']);
        self::assertSame(7, $payload['response_size']);
        self::assertSame('trace-123', $payload['trace_id']);
        self::assertSame('request-123', $payload['request_id']);
        self::assertSame('PHPUnit Browser', $payload['user_agent']);
        self::assertSame('203.0.113.10', $payload['ip']);
        self::assertIsFloat($payload['duration_ms']);
        self::assertGreaterThanOrEqual(0.0, $payload['duration_ms']);
        self::assertSame([], $payload['request_data']);
        self::assertSame([], $payload['response_data']);
        self::assertSame('', $payload['user_data']['mobile']);
        self::assertNotSame('secret-token', json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    public function testLogsFailureAndRethrowsOriginalException(): void
    {
        $middleware = new TestableHttpAccessLogMiddleware();
        $request = $this->request('POST', '/v1/failing');
        $original = new RuntimeException('business failed');

        try {
            $middleware->process($request, new CallbackRequestHandler(
                static function () use ($original): never {
                    throw $original;
                }
            ));
            self::fail('Expected the original exception.');
        } catch (RuntimeException $exception) {
            self::assertSame($original, $exception);
        }

        self::assertCount(1, $middleware->published);
        self::assertSame(500, $middleware->published[0]['response_code']);
        self::assertSame(RuntimeException::class, $middleware->published[0]['error_type']);
    }

    public function testPublishFailureNeverChangesSuccessfulBusinessResponse(): void
    {
        $middleware = new TestableHttpAccessLogMiddleware();
        $middleware->publishFailure = new RuntimeException('rabbit unavailable');
        $response = new Response(204);

        $returned = $middleware->process(
            $this->request('DELETE', '/v1/items/1'),
            new CallbackRequestHandler(static fn (): ResponseInterface => $response)
        );

        self::assertSame($response, $returned);
        self::assertCount(1, $middleware->publishErrors);
        self::assertSame('rabbit unavailable', $middleware->publishErrors[0]->getMessage());
    }

    public function testPublishFailureNeverReplacesOriginalBusinessException(): void
    {
        $middleware = new TestableHttpAccessLogMiddleware();
        $middleware->publishFailure = new RuntimeException('rabbit unavailable');
        $original = new RuntimeException('business failed');

        try {
            $middleware->process(
                $this->request('POST', '/v1/failing'),
                new CallbackRequestHandler(static function () use ($original): never {
                    throw $original;
                })
            );
            self::fail('Expected the original exception.');
        } catch (RuntimeException $exception) {
            self::assertSame($original, $exception);
        }

        self::assertCount(1, $middleware->publishErrors);
    }

    public function testNonPublicStatusCodeMethodDoesNotReplaceBusinessException(): void
    {
        $middleware = new TestableHttpAccessLogMiddleware();
        $original = new ExceptionWithProtectedStatusCode('business failed');

        try {
            $middleware->process(
                $this->request('POST', '/v1/failing'),
                new CallbackRequestHandler(static function () use ($original): never {
                    throw $original;
                })
            );
            self::fail('Expected the original exception.');
        } catch (ExceptionWithProtectedStatusCode $exception) {
            self::assertSame($original, $exception);
        }
    }

    public function testConfigurationAndFailureLoggerErrorsNeverChangeBusinessResponse(): void
    {
        $response = new Response(200);
        $configurationFailure = new TestableHttpAccessLogMiddleware();
        $configurationFailure->shouldPublishFailure = new RuntimeException('configuration unavailable');

        self::assertSame(
            $response,
            $configurationFailure->process(
                $this->request('GET', '/v1/items'),
                new CallbackRequestHandler(static fn (): ResponseInterface => $response)
            )
        );

        $loggingFailure = new TestableHttpAccessLogMiddleware();
        $loggingFailure->publishFailure = new RuntimeException('rabbit unavailable');
        $loggingFailure->logFailure = new RuntimeException('logger unavailable');

        self::assertSame(
            $response,
            $loggingFailure->process(
                $this->request('GET', '/v1/items'),
                new CallbackRequestHandler(static fn (): ResponseInterface => $response)
            )
        );
    }

    public function testAnonymousAndSystemRequestsUseCorrectActorContext(): void
    {
        $anonymous = new TestableHttpAccessLogMiddleware();
        $anonymous->process(
            $this->request('GET', '/v1/public'),
            new CallbackRequestHandler(static fn (): ResponseInterface => new Response())
        );

        self::assertSame('anonymous', $anonymous->published[0]['origin']);
        self::assertNull($anonymous->published[0]['user_id']);

        Context::set(GlobalConstants::SYS_ADMIN_CONTEXT, [
            'id' => 90001,
            'real_name' => '平台管理员',
        ]);
        $system = new TestableHttpAccessLogMiddleware();
        $system->process(
            $this->request('GET', '/v1/system', [GlobalConstants::SYS_TOKEN_KEY => 'system-token']),
            new CallbackRequestHandler(static fn (): ResponseInterface => new Response())
        );

        self::assertSame('sys', $system->published[0]['origin']);
        self::assertSame(90001, $system->published[0]['user_id']);
        self::assertSame('平台管理员', $system->published[0]['username']);
        self::assertSame(0, $system->published[0]['tenant_id']);
    }

    public function testExcludedRoutesAreFilteredBeforePublishing(): void
    {
        $middleware = new TestableHttpAccessLogMiddleware();

        $middleware->process(
            $this->request('GET', '/health'),
            new CallbackRequestHandler(static fn (): ResponseInterface => new Response())
        );
        $middleware->process(
            $this->request('POST', '/v1/roles/query'),
            new CallbackRequestHandler(static fn (): ResponseInterface => new Response())
        );

        self::assertSame([], $middleware->published);
    }

    private function request(string $method, string $path, array $headers = []): ServerRequestInterface
    {
        return new ServerRequest(
            $method,
            'https://example.test' . $path,
            $headers,
            null,
            '1.1',
            [
                'request_time' => 1787364000,
                'request_time_float' => 1787364000.123456,
                'remote_addr' => '127.0.0.1',
            ]
        );
    }
}

final class TestableHttpAccessLogMiddleware extends HttpAccessLogMiddleware
{
    public array $published = [];

    /** @var Throwable[] */
    public array $publishErrors = [];

    public ?Throwable $publishFailure = null;

    public ?Throwable $shouldPublishFailure = null;

    public ?Throwable $logFailure = null;

    public function __construct()
    {
        parent::__construct(new EmptyContainer());
    }

    protected function shouldPublish(ServerRequestInterface $request): bool
    {
        if ($this->shouldPublishFailure) {
            throw $this->shouldPublishFailure;
        }

        return parent::shouldPublish($request);
    }

    protected function publish(array $payload): void
    {
        if ($this->publishFailure) {
            throw $this->publishFailure;
        }

        $this->published[] = $payload;
    }

    protected function logPublishFailure(Throwable $exception, array $context): void
    {
        if ($this->logFailure) {
            throw $this->logFailure;
        }

        $this->publishErrors[] = $exception;
    }
}

final class CallbackRequestHandler implements RequestHandlerInterface
{
    public function __construct(private readonly mixed $callback)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return ($this->callback)($request);
    }
}

final class EmptyContainer implements ContainerInterface
{
    public function get(string $id)
    {
        throw new RuntimeException("Unexpected container entry: {$id}");
    }

    public function has(string $id): bool
    {
        return false;
    }
}

final class ExceptionWithProtectedStatusCode extends RuntimeException
{
    protected function getStatusCode(): int
    {
        return 418;
    }
}

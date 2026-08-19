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
use Hyperf\Context\ApplicationContext;
use Hyperf\Context\Context;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\ContainerInterface as HyperfContainerInterface;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponseInterface;
use Hyperf\Redis\RedisFactory;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use TgkwAdc\Constants\GlobalConstants;
use TgkwAdc\Exception\TokenException;
use TgkwAdc\Helper\JwtHelper;
use TgkwAdc\Middleware\BaseUserMiddleware;

/**
 * @internal
 * @coversNothing
 */
class BaseUserMiddlewareLoggingTest extends TestCase
{
    private ?ContainerInterface $previousContainer = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = ApplicationContext::hasContainer()
            ? ApplicationContext::getContainer()
            : null;
        Context::destroy(GlobalConstants::BASE_USER_CONTEXT);
        Context::set('trace_id', 'trace-test-001');
        Context::set('client_ip', '203.0.113.10');
    }

    protected function tearDown(): void
    {
        Context::destroy(GlobalConstants::BASE_USER_CONTEXT);
        Context::destroy('trace_id');
        Context::destroy('client_ip');
        if ($this->previousContainer instanceof ContainerInterface) {
            ApplicationContext::setContainer($this->previousContainer);
        }
        parent::tearDown();
    }

    public function testCacheMissLogsTheAuthenticationRejectionReason(): void
    {
        $redis = new BaseUserMiddlewareLoggingTestRedis(null);
        $this->setTestContainer($redis);
        $middleware = new TestableBaseUserMiddleware();
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');
        $request = new ServerRequest('GET', '/v1/open/contracts?status=completed', [
            'User-Token' => 'Bearer access-token',
            'User-Agent' => 'PHPUnit',
        ]);

        $response = $middleware->process($request, $handler);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertCount(1, $middleware->authLogs);
        $this->assertSame('warning', $middleware->authLogs[0]['level']);
        $this->assertSame('redis', $middleware->authLogs[0]['context']['stage']);
        $this->assertSame('token_cache_miss', $middleware->authLogs[0]['context']['reason']);
        $this->assertSame('GET', $middleware->authLogs[0]['context']['method']);
        $this->assertSame('/v1/open/contracts', $middleware->authLogs[0]['context']['path']);
        $this->assertSame('trace-test-001', $middleware->authLogs[0]['context']['trace_id']);
        $this->assertSame('203.0.113.10', $middleware->authLogs[0]['context']['client_ip']);
        $this->assertSame(substr(hash('sha256', 'access-token'), 0, 16), $middleware->authLogs[0]['context']['token_fingerprint']);
        $this->assertSame(GlobalConstants::ORG_TOKEN_REDIS_KEY_PREFIX . 'access-token', $redis->requestedKey);
        $this->assertSame(GlobalConstants::USER_TOKEN_KEY, $middleware->authLogs[0]['context']['token_header']);
        $this->assertFalse($middleware->authLogs[0]['context']['jwt_valid']);
        $this->assertNotEmpty($middleware->authLogs[0]['context']['jwt_exception_class']);
        $this->assertArrayNotHasKey('token', $middleware->authLogs[0]['context']);
    }

    public function testCacheMissLogsValidatedBusinessTokenClaims(): void
    {
        $redis = new BaseUserMiddlewareLoggingTestRedis(null);
        $this->setTestContainer($redis);
        $token = JwtHelper::createToken(GlobalConstants::ORG_TOKEN_TYPE, [
            'id' => 923,
            'session_id' => 'idp-session-001',
            'idp_sub' => 'idp-user-923',
            'token_type' => 'access',
        ], 3600);
        $middleware = new TestableBaseUserMiddleware();
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');
        $request = new ServerRequest('GET', '/v1/user/test', [
            'Org-Token' => $token,
        ]);

        $response = $middleware->process($request, $handler);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertTrue($middleware->authLogs[0]['context']['jwt_valid']);
        $this->assertSame(923, $middleware->authLogs[0]['context']['jwt_user_id']);
        $this->assertSame('idp-session-001', $middleware->authLogs[0]['context']['jwt_session_id']);
        $this->assertSame('idp-user-923', $middleware->authLogs[0]['context']['jwt_idp_sub']);
        $this->assertSame('access', $middleware->authLogs[0]['context']['jwt_token_type']);
        $this->assertIsInt($middleware->authLogs[0]['context']['jwt_iat']);
        $this->assertIsInt($middleware->authLogs[0]['context']['jwt_exp']);
    }

    public function testOrgTokenLoadsBusinessTokenCacheWithoutTenantAuthorization(): void
    {
        $redis = new BaseUserMiddlewareLoggingTestRedis(json_encode([
            'id' => 923,
            'current_tenant_id' => '1001',
        ], JSON_THROW_ON_ERROR));
        $this->setTestContainer($redis);
        $middleware = new TestableBaseUserMiddleware();
        $expected = new Response(200);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())->method('handle')->willReturn($expected);
        $request = new ServerRequest('GET', '/v1/user/test', [
            'Org-Token' => 'Bearer access-token',
        ]);

        $actual = $middleware->process($request, $handler);

        $this->assertSame($expected, $actual);
        $this->assertSame(GlobalConstants::ORG_TOKEN_REDIS_KEY_PREFIX . 'access-token', $redis->requestedKey);
        $this->assertSame([
            'id' => 923,
            'current_tenant_id' => '1001',
        ], Context::get(GlobalConstants::BASE_USER_CONTEXT));
        $this->assertSame(GlobalConstants::ORG_TOKEN_KEY, $middleware->authLogs[0]['context']['token_header']);
    }

    public function testMissingHeaderLogsBeforeTheTokenExceptionIsRethrown(): void
    {
        $this->setTestContainer(new BaseUserMiddlewareLoggingTestRedis(null));
        $middleware = new TestableBaseUserMiddleware();
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');
        $request = new ServerRequest('GET', '/v1/open/contracts');

        try {
            $middleware->process($request, $handler);
            $this->fail('Expected TokenException was not thrown.');
        } catch (TokenException) {
            $this->assertCount(1, $middleware->authLogs);
            $this->assertSame('warning', $middleware->authLogs[0]['level']);
            $this->assertSame('token_extraction', $middleware->authLogs[0]['context']['stage']);
            $this->assertSame('token_header_missing_or_invalid', $middleware->authLogs[0]['context']['reason']);
            $this->assertFalse($middleware->authLogs[0]['context']['token_header_present']);
            $this->assertNull($middleware->authLogs[0]['context']['token_fingerprint']);
        }
    }

    private function setTestContainer(BaseUserMiddlewareLoggingTestRedis $redis): void
    {
        $config = $this->createMock(ConfigInterface::class);
        $config->method('get')->willReturn([
            'JWT_SYSTEM_KEY' => 'test-system-key',
            'JWT_ORG_KEY' => 'test-org-key',
            'JWT_USER_KEY' => 'test-user-key',
        ]);

        $httpResponse = $this->createMock(HttpResponseInterface::class);
        $httpResponse->method('json')->willReturn(new Response());

        ApplicationContext::setContainer(new BaseUserMiddlewareLoggingTestContainer(
            $config,
            new BaseUserMiddlewareLoggingTestRedisFactory($redis),
            $httpResponse
        ));
    }
}

final class TestableBaseUserMiddleware extends BaseUserMiddleware
{
    public array $authLogs = [];

    protected function logAuth(string $level, string $message, array $context): void
    {
        $this->authLogs[] = compact('level', 'message', 'context');
    }
}

final class BaseUserMiddlewareLoggingTestContainer implements HyperfContainerInterface
{
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly BaseUserMiddlewareLoggingTestRedisFactory $redisFactory,
        private readonly HttpResponseInterface $httpResponse
    ) {
    }

    public function get(string $id)
    {
        return match ($id) {
            ContainerInterface::class,
            HyperfContainerInterface::class => $this,
            ConfigInterface::class => $this->config,
            RedisFactory::class => $this->redisFactory,
            HttpResponseInterface::class => $this->httpResponse,
            default => throw new RuntimeException("Unexpected container entry: {$id}"),
        };
    }

    public function has(string $id): bool
    {
        return in_array($id, [
            ContainerInterface::class,
            HyperfContainerInterface::class,
            ConfigInterface::class,
            RedisFactory::class,
            HttpResponseInterface::class,
        ], true);
    }

    public function make(string $name, array $parameters = [])
    {
        return $this->get($name);
    }

    public function set(string $name, $entry): void
    {
    }

    public function unbind(string $name): void
    {
    }

    public function define(string $name, $definition): void
    {
    }
}

final class BaseUserMiddlewareLoggingTestRedisFactory
{
    public function __construct(private readonly BaseUserMiddlewareLoggingTestRedis $redis)
    {
    }

    public function get(string $poolName): BaseUserMiddlewareLoggingTestRedis
    {
        return $this->redis;
    }
}

final class BaseUserMiddlewareLoggingTestRedis
{
    public ?string $requestedKey = null;

    public function __construct(private readonly ?string $payload)
    {
    }

    public function get(string $key): ?string
    {
        $this->requestedKey = $key;

        return $this->payload;
    }
}

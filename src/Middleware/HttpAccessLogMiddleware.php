<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Middleware;

use DateTimeImmutable;
use DateTimeZone;
use Hyperf\Amqp\Producer;
use Hyperf\Context\Context;
use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ramsey\Uuid\Uuid;
use TgkwAdc\Amqp\Producer\OperationLogProducer;
use TgkwAdc\Constants\GlobalConstants;
use TgkwAdc\Helper\Log\LogHelper;
use TgkwAdc\Utils\IpTool;
use Throwable;

/**
 * 轻量 HTTP 访问日志中间件。
 *
 * 该日志用于接口访问追踪，不承担业务数据前后值审计职责。
 */
class HttpAccessLogMiddleware implements MiddlewareInterface
{
    private const DEFAULT_EXCLUDE_ROUTES = [
        'GET /health',
        'GET /favicon.ico',
        'POST */query',
    ];

    public function __construct(protected ContainerInterface $container)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $startedAt = microtime(true);
        $response = null;
        $statusCode = 500;
        $errorType = null;

        try {
            $response = $handler->handle($request);
            $statusCode = $response->getStatusCode();

            return $response;
        } catch (Throwable $exception) {
            $statusCode = $this->exceptionStatusCode($exception);
            $errorType = $exception::class;

            throw $exception;
        } finally {
            if ($this->shouldPublish($request)) {
                try {
                    $payload = $this->buildPayload(
                        $request,
                        $response,
                        $statusCode,
                        $errorType,
                        $startedAt
                    );
                    $this->publish($payload);
                } catch (Throwable $exception) {
                    $this->logPublishFailure($exception, [
                        'method' => $request->getMethod(),
                        'router' => $request->getUri()->getPath(),
                        'trace_id' => (string) Context::get('trace_id', ''),
                    ]);
                }
            }
        }
    }

    protected function buildPayload(
        ServerRequestInterface $request,
        ?ResponseInterface $response,
        int $statusCode,
        ?string $errorType,
        float $startedAt
    ): array {
        $eventId = Uuid::uuid7()->toString();
        $occurredTimestamp = (float) ($request->getServerParams()['request_time_float'] ?? $startedAt);
        $occurredAt = $this->formatTimestamp($occurredTimestamp, 'Y-m-d H:i:s.u');
        $traceId = (string) Context::get('trace_id', '');
        $requestId = $request->getHeaderLine('X-Request-Id')
            ?: (string) Context::get('request_id', $traceId ?: $eventId);
        $actor = $this->actorContext($request);

        return [
            'event_id' => $eventId,
            'tenant_id' => $actor['tenant_id'],
            'time' => $this->formatTimestamp($occurredTimestamp, 'Y-m-d H:i:s'),
            'occurred_at' => $occurredAt,
            'method' => strtoupper($request->getMethod()),
            'router' => $request->getUri()->getPath(),
            'protocol' => $request->getProtocolVersion(),
            'ip' => IpTool::getRealIp($request),
            'app_id' => (string) Context::get('app_id', ''),
            'service_name' => (string) env('APP_NAME', ''),
            'user_id' => $actor['user_id'],
            'username' => $actor['username'],
            'origin' => $actor['origin'],
            'user_data' => $actor['legacy_user_data'],
            'request_data' => [],
            'response_code' => $statusCode,
            'response_data' => [],
            'response_size' => $this->responseSize($response),
            'duration_ms' => round(max(0.0, (microtime(true) - $startedAt) * 1000), 3),
            'trace_id' => $traceId,
            'request_id' => $requestId,
            'user_agent' => $request->getHeaderLine('User-Agent'),
            'error_type' => $errorType,
        ];
    }

    protected function shouldPublish(ServerRequestInterface $request): bool
    {
        if (! (bool) $this->config('enabled', true)) {
            return false;
        }

        $route = strtoupper($request->getMethod()) . ' ' . $request->getUri()->getPath();
        $patterns = $this->config('exclude_routes', self::DEFAULT_EXCLUDE_ROUTES);
        if (! is_array($patterns)) {
            $patterns = self::DEFAULT_EXCLUDE_ROUTES;
        }

        foreach ($patterns as $pattern) {
            if (is_string($pattern) && fnmatch($pattern, $route)) {
                return false;
            }
        }

        return true;
    }

    protected function publish(array $payload): void
    {
        /** @var Producer $producer */
        $producer = $this->container->get(Producer::class);
        $producer->produce(new OperationLogProducer($payload));
    }

    protected function logPublishFailure(Throwable $exception, array $context): void
    {
        LogHelper::error('http_access_log_publish_failed', [
            ...$context,
            'error_type' => $exception::class,
            'error' => $exception->getMessage(),
        ]);
    }

    protected function config(string $key, mixed $default = null): mixed
    {
        if (! $this->container->has(ConfigInterface::class)) {
            return $default;
        }

        /** @var ConfigInterface $config */
        $config = $this->container->get(ConfigInterface::class);

        return $config->get('access_log.' . $key, $default);
    }

    private function actorContext(ServerRequestInterface $request): array
    {
        $origin = 'anonymous';
        $user = null;
        $tenantId = 0;

        if ($request->hasHeader(GlobalConstants::SYS_TOKEN_KEY)) {
            $origin = strtolower(GlobalConstants::SYS_TOKEN_TYPE);
            $user = Context::get(GlobalConstants::SYS_ADMIN_CONTEXT);
        } elseif ($request->hasHeader(GlobalConstants::ORG_TOKEN_KEY)) {
            $origin = strtolower(GlobalConstants::ORG_TOKEN_TYPE);
            $user = Context::get(GlobalConstants::ORG_USER_CONTEXT);
            if (is_array($user)) {
                $tenantId = (int) ($user['current_tenant_id'] ?? $user['tenant_id'] ?? 0);
            }
        } elseif ($request->hasHeader(GlobalConstants::USER_TOKEN_KEY)) {
            $origin = strtolower(GlobalConstants::USER_TOKEN_TYPE);
            $user = Context::get(GlobalConstants::BASE_USER_CONTEXT);
        }

        if (! is_array($user)) {
            $user = [];
        }

        $userId = isset($user['id']) ? (int) $user['id'] : null;
        $username = (string) ($user['real_name'] ?? $user['name'] ?? '');
        $legacyUserData = [];
        if ($userId !== null) {
            $legacyUserData = [
                'id' => $userId,
                'real_name' => $username,
                'mobile' => '',
                'origin' => $origin,
                'tenant_id' => $tenantId,
            ];
        }

        return [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'username' => $username,
            'origin' => $origin,
            'legacy_user_data' => $legacyUserData,
        ];
    }

    private function responseSize(?ResponseInterface $response): ?int
    {
        if (! $response) {
            return null;
        }

        $contentLength = $response->getHeaderLine('Content-Length');
        if ($contentLength !== '' && ctype_digit($contentLength)) {
            return (int) $contentLength;
        }

        return $response->getBody()->getSize();
    }

    private function exceptionStatusCode(Throwable $exception): int
    {
        if (method_exists($exception, 'getStatusCode')) {
            $statusCode = (int) $exception->getStatusCode();
            if ($statusCode >= 400 && $statusCode <= 599) {
                return $statusCode;
            }
        }

        return 500;
    }

    private function formatTimestamp(float $timestamp, string $format): string
    {
        $date = DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', $timestamp));
        if (! $date) {
            $date = new DateTimeImmutable();
        }

        return $date
            ->setTimezone(new DateTimeZone(date_default_timezone_get()))
            ->format($format);
    }
}

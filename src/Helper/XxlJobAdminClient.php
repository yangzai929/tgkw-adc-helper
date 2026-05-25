<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Helper;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Hyperf\Codec\Json;
use RuntimeException;
use Throwable;

/**
 * XXL-Job Admin HTTP 客户端，支持 2.x / 3.x 管理端。
 */
class XxlJobAdminClient
{
    private string $adminAddress;

    private string $baseUri;

    private string $pathPrefix;

    private string $username;

    private string $password;

    private string $accessToken;

    private ?string $cookieHeader = null;

    private Client $client;

    public function __construct(?array $xxlJobConfig = null)
    {
        $config = $xxlJobConfig ?? (function_exists('cfg') ? cfg('xxl_job', []) : []);
        $this->adminAddress = rtrim((string) ($config['admin_address'] ?? env('XXL_JOB_ADMIN_ADDRESS', '')), '/');
        if ($this->adminAddress === '') {
            throw new RuntimeException('XXL_JOB_ADMIN_ADDRESS is not configured');
        }

        $parsed = parse_url($this->adminAddress);
        $scheme = $parsed['scheme'] ?? 'http';
        $host = $parsed['host'] ?? '127.0.0.1';
        $port = $parsed['port'] ?? ($scheme === 'https' ? 443 : 80);
        $this->baseUri = sprintf('%s://%s:%s', $scheme, $host, $port);
        $this->pathPrefix = $parsed['path'] ?? '';

        $this->username = (string) env('XXL_JOB_ADMIN_USERNAME', 'admin');
        $this->password = (string) env('XXL_JOB_ADMIN_PASSWORD', '123456');
        $this->accessToken = (string) ($config['access_token'] ?? env('XXL_JOB_ACCESS_TOKEN', ''));

        $guzzleConfig = $config['guzzle_config'] ?? [];
        $this->client = new Client(array_merge([
            'base_uri' => $this->baseUri,
            'timeout' => 10,
            'headers' => [
                'charset' => 'UTF-8',
            ],
        ], is_array($guzzleConfig) ? $guzzleConfig : []));
    }

    public function resolveJobGroupId(string $appName, string $title, bool $autoCreate = true): int
    {
        $groups = $this->pageJobGroups($appName, $title);
        foreach ($groups as $group) {
            if (($group['appname'] ?? '') === $appName && ($group['title'] ?? '') === $title) {
                return (int) $group['id'];
            }
        }

        if (! $autoCreate) {
            throw new RuntimeException(sprintf('xxl-job executor group not found: %s / %s', $appName, $title));
        }

        $this->postForm('/jobgroup/save', [
            'appname' => $appName,
            'title' => $title,
            'addressType' => 0,
        ]);

        $groups = $this->pageJobGroups($appName, $title);
        foreach ($groups as $group) {
            if (($group['appname'] ?? '') === $appName && ($group['title'] ?? '') === $title) {
                return (int) $group['id'];
            }
        }

        throw new RuntimeException(sprintf('xxl-job executor group create failed: %s / %s', $appName, $title));
    }

    public function findJob(int $jobGroupId, string $executorHandler): ?array
    {
        $jobs = $this->pageJobs($jobGroupId, $executorHandler);
        foreach ($jobs as $job) {
            if (($job['executorHandler'] ?? '') === $executorHandler) {
                return $job;
            }
        }

        return null;
    }

    public function addJob(array $payload): int
    {
        $result = $this->postForm('/jobinfo/add', $payload);
        if (! isset($result['content']) || ! is_numeric($result['content'])) {
            throw new RuntimeException('xxl-job jobinfo/add response missing job id');
        }

        return (int) $result['content'];
    }

    public function updateJob(array $payload): void
    {
        $this->postForm('/jobinfo/update', $payload);
    }

    public function startJob(int $jobId): void
    {
        $this->postForm('/jobinfo/start', ['id' => $jobId]);
    }

    public function buildJobPayload(int $jobGroupId, array $job): array
    {
        $isV3 = version_compare((string) ($job['xxlVersion'] ?? '3.2.0'), '3.0.0', '>=');
        $payload = [
            'jobGroup' => $jobGroupId,
            'jobDesc' => (string) ($job['jobDesc'] ?? ''),
            'author' => (string) ($job['author'] ?? '机器注册(adc)'),
            'alarmEmail' => '',
            'executorRouteStrategy' => (string) (($job['routeStrategy'] ?? '') ?: 'FIRST'),
            'executorHandler' => (string) ($job['jobHandler'] ?? ''),
            'executorParam' => (string) ($job['jobParam'] ?? ''),
            'executorBlockStrategy' => (string) (($job['blockStrategy'] ?? '') ?: 'SERIAL_EXECUTION'),
            'executorTimeout' => (int) ($job['jobTimeout'] ?? 0),
            'executorFailRetryCount' => (int) ($job['jobRetry'] ?? 0),
            'glueType' => 'BEAN',
            'glueRemark' => 'GLUE代码初始化',
            'misfireStrategy' => 'DO_NOTHING',
        ];

        if ($isV3) {
            $payload['scheduleType'] = (string) (($job['scheduleType'] ?? '') ?: 'CRON');
            $payload['scheduleConf'] = (string) ($job['cron'] ?? '');
        } else {
            $payload['jobCron'] = (string) ($job['cron'] ?? '');
        }

        return $payload;
    }

    public function shouldUpdateExistingJob(array $existing, array $desired): bool
    {
        $compareFields = [
            'jobDesc',
            'author',
            'scheduleConf',
            'jobCron',
            'executorRouteStrategy',
            'executorBlockStrategy',
            'executorParam',
            'executorTimeout',
            'executorFailRetryCount',
        ];

        foreach ($compareFields as $field) {
            $existingValue = $existing[$field] ?? null;
            $desiredValue = $desired[$field] ?? null;
            if ($field === 'scheduleConf' && ($desiredValue === null || $desiredValue === '')) {
                $desiredValue = $desired['jobCron'] ?? null;
            }
            if ((string) $existingValue !== (string) $desiredValue) {
                return true;
            }
        }

        return false;
    }

    public function registerJob(int $jobGroupId, array $job, bool $autoStart = false): void
    {
        $this->ensureLogin();
        $payload = $this->buildJobPayload($jobGroupId, $job);
        $handler = $payload['executorHandler'];
        $existing = $this->findJob($jobGroupId, $handler);

        if ($existing === null) {
            $jobId = $this->addJob($payload);
            if ($autoStart) {
                $this->startJob($jobId);
            }
            return;
        }

        if ($this->shouldUpdateExistingJob($existing, $payload)) {
            $payload['id'] = (int) $existing['id'];
            $this->updateJob($payload);
        }

        if ($autoStart && (int) ($existing['triggerStatus'] ?? 0) !== 1) {
            $this->startJob((int) $existing['id']);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pageJobGroups(string $appName, string $title): array
    {
        $result = $this->postForm('/jobgroup/pageList', [
            'start' => 0,
            'length' => 100,
            'appname' => $appName,
            'title' => $title,
        ]);

        return $this->extractList($result);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pageJobs(int $jobGroupId, string $executorHandler): array
    {
        $result = $this->postForm('/jobinfo/pageList', [
            'start' => 0,
            'length' => 100,
            'jobGroup' => $jobGroupId,
            'triggerStatus' => -1,
            'jobDesc' => '',
            'executorHandler' => $executorHandler,
            'author' => '',
        ]);

        return $this->extractList($result);
    }

    /**
     * @return array<string, mixed>
     */
    private function postForm(string $uri, array $form): array
    {
        $this->ensureLogin();

        $lastException = null;
        for ($attempt = 0; $attempt < 2; ++$attempt) {
            try {
                $options = [
                    RequestOptions::FORM_PARAMS => $form,
                ];
                $headers = [];
                if ($this->cookieHeader !== null) {
                    $headers['Cookie'] = $this->cookieHeader;
                }
                if ($this->accessToken !== '') {
                    $headers['XXL-JOB-ACCESS-TOKEN'] = $this->accessToken;
                }
                if ($headers !== []) {
                    $options[RequestOptions::HEADERS] = $headers;
                }

                $response = $this->client->post($this->pathPrefix . $uri, $options);
                $body = (string) $response->getBody();
                if ($body === '' || $body[0] !== '{') {
                    throw new RuntimeException(sprintf('xxl-job admin invalid response: %s', mb_substr($body, 0, 200)));
                }

                /** @var array<string, mixed> $result */
                $result = Json::decode($body);
                if ($this->looksLikeFailure($result)) {
                    $message = (string) ($result['msg'] ?? 'unknown error');
                    if ($this->shouldRetryLogin($message, $attempt)) {
                        $this->login();
                        continue;
                    }
                    throw new RuntimeException(sprintf('xxl-job admin request failed: %s', $message));
                }

                return $result;
            } catch (GuzzleException $exception) {
                $lastException = $exception;
            } catch (Throwable $exception) {
                if ($this->shouldRetryLogin($exception->getMessage(), $attempt)) {
                    $this->login();
                    continue;
                }
                throw $exception;
            }
        }

        throw new RuntimeException('xxl-job admin request failed: ' . ($lastException?->getMessage() ?? 'unknown error'));
    }

    private function login(): void
    {
        $this->cookieHeader = null;
        $payloads = [
            ['uri' => '/auth/doLogin', 'fields' => ['userName' => $this->username, 'password' => $this->password]],
            ['uri' => '/login', 'fields' => ['userName' => $this->username, 'password' => $this->password, 'ifRemember' => 'on']],
        ];

        $lastError = 'login failed';
        foreach ($payloads as $payload) {
            try {
                $response = $this->client->post($this->pathPrefix . $payload['uri'], [
                    RequestOptions::FORM_PARAMS => $payload['fields'],
                ]);
                $cookieHeader = $this->extractCookieHeader($response->getHeader('Set-Cookie'));
                if ($cookieHeader === null) {
                    $lastError = 'login succeeded but cookie missing';
                    continue;
                }

                $body = (string) $response->getBody();
                if ($body !== '' && $body[0] === '{') {
                    /** @var array<string, mixed> $result */
                    $result = Json::decode($body);
                    if ($this->looksLikeFailure($result)) {
                        $lastError = (string) ($result['msg'] ?? 'login rejected');
                        continue;
                    }
                }

                $this->cookieHeader = $cookieHeader;
                return;
            } catch (Throwable $exception) {
                $lastError = $exception->getMessage();
            }
        }

        throw new RuntimeException('xxl-job admin login failed: ' . $lastError);
    }

    private function ensureLogin(): void
    {
        if ($this->cookieHeader === null) {
            $this->login();
        }
    }

    /**
     * @param array<string, mixed> $result
     * @return array<int, array<string, mixed>>
     */
    private function extractList(array $result): array
    {
        if (isset($result['data']) && is_array($result['data'])) {
            return $result['data'];
        }

        if (isset($result['content']) && is_array($result['content'])) {
            $content = $result['content'];
            if (isset($content['data']) && is_array($content['data'])) {
                return $content['data'];
            }
        }

        return [];
    }

    /**
     * @param array<string, mixed> $result
     */
    private function looksLikeFailure(array $result): bool
    {
        if (! array_key_exists('code', $result)) {
            return false;
        }

        return (int) $result['code'] !== 200;
    }

    private function shouldRetryLogin(string $message, int $attempt): bool
    {
        if ($attempt >= 1) {
            return false;
        }

        if ($this->cookieHeader === null) {
            $this->login();
            return true;
        }

        return str_contains(strtolower($message), 'login') || str_contains($message, '权限');
    }

    /**
     * @param array<int, string> $setCookies
     */
    private function extractCookieHeader(array $setCookies): ?string
    {
        $pairs = [];
        foreach ($setCookies as $setCookie) {
            $parts = explode(';', $setCookie);
            $pair = trim($parts[0] ?? '');
            if ($pair === '') {
                continue;
            }
            [$name] = explode('=', $pair, 2);
            if (in_array($name, ['xxl_job_login_token', 'XXL_JOB_LOGIN_IDENTITY'], true)) {
                $pairs[] = $pair;
            }
        }

        return $pairs === [] ? null : implode('; ', $pairs);
    }
}

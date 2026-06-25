<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Listener;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BeforeMainServerStart;

use function Hyperf\Support\env;

class RabbitMqVhostInitListener implements ListenerInterface
{
    public function __construct(
        private ConfigInterface $config,
        private StdoutLoggerInterface $logger,
    ) {
    }

    public function listen(): array
    {
        return [
            BeforeMainServerStart::class,
        ];
    }

    public function process(object $event): void
    {
        if (env('AMQP_VHOST_AUTO_CREATE') !== true || ! env('AMQP_PORT_ADMIN')) {
            return;
        }

        $host = (string) env('AMQP_HOST', 'localhost');
        $port = (string) env('AMQP_PORT_ADMIN', '15672');
        $user = (string) env('AMQP_USER', 'guest');
        $password = (string) env('AMQP_PASSWORD', 'guest');

        foreach ($this->vhosts() as $vhost) {
            try {
                $response = (new Client())->request(
                    'PUT',
                    sprintf('http://%s:%s/api/vhosts/%s', $host, $port, rawurlencode($vhost)),
                    ['auth' => [$user, $password]]
                );

                $this->logger->info('rabbit-mq vhost pre-init ok', [
                    'vhost' => $vhost,
                    'status_code' => $response->getStatusCode(),
                ]);
            } catch (GuzzleException $exception) {
                $this->logger->error('rabbit-mq vhost pre-init failed', [
                    'vhost' => $vhost,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    /**
     * @return string[]
     */
    private function vhosts(): array
    {
        $vhosts = [];
        foreach ((array) $this->config->get('amqp', []) as $pool) {
            if (is_array($pool) && isset($pool['vhost']) && (string) $pool['vhost'] !== '') {
                $vhosts[] = (string) $pool['vhost'];
            }
        }

        return array_values(array_unique($vhosts));
    }
}

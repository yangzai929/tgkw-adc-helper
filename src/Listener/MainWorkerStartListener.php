<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Listener;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Hyperf\Amqp\Annotation\Consumer;
use Hyperf\Amqp\Annotation\Producer;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ApplicationInterface;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\MainWorkerStart;
use Swoole\Process;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use TgkwAdc\Helper\Log\LogHelper;
use TgkwAdc\Helper\OrgPermissionHelper;
use TgkwAdc\Helper\XxlJobTaskHelper;
use TgkwAdc\JsonRpc\User\UserServiceInterface;

#[Listener]
class MainWorkerStartListener implements ListenerInterface
{
    public function listen(): array
    {
        return [
            MainWorkerStart::class,
        ];
    }

    public function process(object $event): void
    {
        try {
            //  Redis 分布式锁 确保启动时只有一个进程执行
            $lockKey = 'mainWorkerStart';
            $ttl = 300; // 锁过期时间，单位秒
            // 尝试获取锁
            $isLocked = redis()->set('mainWorkerStart', 'rate', ['NX', 'EX' => 10]);

            LogHelper::info('Redis set result: ' . ($isLocked ? 'success' : 'failed'));

            if (! $isLocked) {
                LogHelper::info('MainWorkerStartListener skipped due to Redis key already exists');

                return;
            }
        } catch (Exception $e) {
            LogHelper::error('Redis connection failed: ' . $e->getMessage());

            return;
        }

        //        //生产环境，执行下 preStart，应用启动前的预处理操作
        //        if (! env('APP_ENV') === 'dev') {
        //            $input = new ArrayInput(['command' => 'preStart']);
        //            $output = new ConsoleOutput();
        //            $application = container()->get(ApplicationInterface::class);
        //            $application->setAutoExit(false);
        //            $exitCode = $application->run($input, $output);
        //            LogHelper::info('preStart result：', [$exitCode]);
        //        }

        // 检测mq的queue、exchange是否以当前服务名开始，避免复制其他代码导致queue相同，引发问题（system.开头的代表系统级）
        if (env('AMQP_USER') && env('AMQP_PASSWORD') && env('APP_NAME')) {
            $consumerExchangeArr = [];
            // Consumer的queue必须以当前服务名开始
            $class = AnnotationCollector::getClassesByAnnotation(Consumer::class);
            if (! empty($class)) {
                foreach ($class as $item) {
                    if (! empty($item->queue) && stripos($item->queue, env('APP_NAME')) !== 0) {
                        LogHelper::error('❌ 失败发现mq消费者的queue不符合规则，必须以服务名（' . env('APP_NAME') . '）开始：' . $item->queue);
                        Process::kill((int) file_get_contents(\Hyperf\Config\config('server.settings.pid_file')));
                        break;
                    }
                    $consumerExchangeArr[] = $item->exchange;
                }
            }

            // Producer的exchange必须要以本服务名开始，特别是当本服务的Consumer存在的时候，避免命令为其他服务。
            $class = AnnotationCollector::getClassesByAnnotation(Producer::class);
            if (! empty($class)) {
                foreach ($class as $item) {
                    if (! empty($item->exchange) && stripos($item->exchange, env('APP_NAME')) !== 0 && stripos($item->exchange, 'system') !== 0 && in_array($item->exchange, $consumerExchangeArr)) {
                        LogHelper::error('❌ 失败发现mq投递者的exchange不符合规则，必须以服务名（' . env('APP_NAME') . '）开始：' . $item->exchange);
                        Process::kill((int) file_get_contents(\Hyperf\Config\config('server.settings.pid_file')));
                        break;
                    }
                }
            }
        }

        // 初始化打开 xxl-job
        LogHelper::info('xxl-job-task init now');
        if (env('XXL_JOB_ENABLE') === true) {
            LogHelper::info('xxl-job is enable!✅ ');
            $XxlJobTaskHelper = new XxlJobTaskHelper();
            $XxlJobTaskHelper->build(true);
        }

        // 初始化创建 rabbit-mq vhost
        LogHelper::info('rabbit-mq vhost init now');
        if (env('AMQP_VHOST_AUTO_CREATE') === true && env('AMQP_PORT_ADMIN')) {
            $clientHttp = new Client();
            try {
                $response = $clientHttp->request(
                    'PUT',
                    sprintf(
                        'http://%s:%s/api/vhosts/%s',
                        env('AMQP_HOST'),
                        env('AMQP_PORT_ADMIN'),
                        env('AMQP_VHOST', 'adc')
                    ),
                    ['auth' => [env('AMQP_USER'), env('AMQP_PASSWORD')],
                        'content-type' => 'application/json',
                    ]
                );

                $mqResultCode = $response->getStatusCode();
                if ($mqResultCode == 201 || $mqResultCode == 204) {
                    LogHelper::info('rabbit-mq vhost create OK !✅ ');
                }
            } catch (GuzzleException $e) {
                LogHelper::error('rabbit vhost create error：' . $e->getMessage());
            }
        }

        LogHelper::info('开始同步菜单');

        $data = OrgPermissionHelper::build();
        LogHelper::info('菜单数据', [$data]);
        if (env('APP_NAME') == 'user' && class_exists('\App\JsonRpc\Provider\UserService')) {
            $userServiceRes = make('\App\JsonRpc\Provider\UserService')->addMenu($data);
        } else {
            $userServiceRes = ApplicationContext::getContainer()->get(UserServiceInterface::class)->addMenu($data);
        }

        LogHelper::info('启动完成！（耗时：' . number_format(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 2) . 's）');
    }
}

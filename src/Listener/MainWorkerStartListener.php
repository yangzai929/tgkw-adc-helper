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
            $ttl = 10; // 锁过期时间，单位秒
            // 尝试获取锁
            $isLocked = redis()->set($lockKey, 'rate', ['NX', 'EX' => $ttl]);

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

        // 检测mq的queue、exchange命名规范（修正跨服务通信问题）
        if (env('AMQP_USER') && env('AMQP_PASSWORD') && env('APP_NAME')) {
            $currentServiceName = env('APP_NAME');
            $localConsumerExchanges = []; // 记录当前服务Consumer使用的Exchange（本服务消费的Exchange）

            // 1. Consumer校验：Queue和Exchange必须以当前服务名开头（或系统级），避免消费冲突
            $consumerClasses = AnnotationCollector::getClassesByAnnotation(Consumer::class);
            if (! empty($consumerClasses)) {
                foreach ($consumerClasses as $item) {
                    // 校验Queue：必须以服务名开头（系统级可例外，可选）
                    if (! empty($item->queue) && stripos($item->queue, $currentServiceName) !== 0 && stripos($item->queue, 'system') !== 0) {
                        $errorMsg = "❌ MQ Consumer 校验失败：Queue[{$item->queue}] 必须以服务名[{$currentServiceName}]或系统前缀[system]开头";
                        LogHelper::error($errorMsg);
                        echo $errorMsg . PHP_EOL;
                        Process::kill((int) file_get_contents(\Hyperf\Config\config('server.settings.pid_file')));
                        break;
                    }

                    // 校验Exchange：必须以服务名开头（系统级可例外）
                    if (! empty($item->exchange) && stripos($item->exchange, $currentServiceName) !== 0 && stripos($item->exchange, 'system') !== 0) {
                        $errorMsg = "❌ MQ Consumer 校验失败：Exchange[{$item->exchange}] 必须以服务名[{$currentServiceName}]或系统前缀[system]开头";
                        LogHelper::error($errorMsg);
                        echo $errorMsg . PHP_EOL;
                        Process::kill((int) file_get_contents(\Hyperf\Config\config('server.settings.pid_file')));
                        break;
                    }

                    $localConsumerExchanges[] = $item->exchange; // 记录本服务消费的Exchange
                }
            }

            // 2. Producer校验：仅限制Exchange命名规范（非空、不非法），不限制归属（允许投递到其他服务的Exchange）
            $producerClasses = AnnotationCollector::getClassesByAnnotation(Producer::class);
            if (! empty($producerClasses)) {
                foreach ($producerClasses as $item) {
                    // 基础校验：Exchange不能为空（避免无效配置）
                    if (empty($item->exchange)) {
                        $errorMsg = '❌ MQ Producer 校验失败：Exchange 不能为空';
                        LogHelper::error($errorMsg);
                        echo $errorMsg . PHP_EOL;
                        Process::kill((int) file_get_contents(\Hyperf\Config\config('server.settings.pid_file')));
                        break;
                    }

                    // Exchange必须包含服务名（目标服务名或当前服务名），避免无意义命名
                    if (! preg_match('/^[a-zA-Z0-9.-]+$/', $item->exchange) || substr_count($item->exchange, '.') < 1) {
                        $errorMsg = "❌ MQ Producer 校验失败：Exchange[{$item->exchange}] 命名不规范！" . PHP_EOL
                                  . '允许字符：字母（a-z/A-Z）、数字（0-9）、点（.）、连字符（-）' . PHP_EOL
                                  . '建议格式：[服务名].[功能模块].[操作]（如 orderService.order.createOrder）';
                        LogHelper::error($errorMsg);
                        echo $errorMsg . PHP_EOL;
                        Process::kill((int) file_get_contents(\Hyperf\Config\config('server.settings.pid_file')));
                        break;
                    }

                    // 保留原逻辑：若Producer投递的Exchange是本服务Consumer正在使用的，需符合Consumer的规则（避免本服务Exchange命名冲突）
                    if (in_array($item->exchange, $localConsumerExchanges) && stripos($item->exchange, $currentServiceName) !== 0 && stripos($item->exchange, 'system') !== 0) {
                        $errorMsg = "❌ MQ Producer 校验失败：Exchange[{$item->exchange}] 是本服务Consumer使用的，必须以服务名[{$currentServiceName}]或系统前缀[system]开头";
                        LogHelper::error($errorMsg);
                        echo $errorMsg . PHP_EOL;
                        Process::kill((int) file_get_contents(\Hyperf\Config\config('server.settings.pid_file')));
                        break;
                    }
                }
            }
        }

        // 初始化打开 xxl-job
        LogHelper::info('xxl-job-task init now');
        if (env('XXL_JOB_ENABLE') === true) {
            LogHelper::info('xxl-job is enable! ✅ ');
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
                    LogHelper::info('rabbit-mq vhost create OK ! ✅ ');
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

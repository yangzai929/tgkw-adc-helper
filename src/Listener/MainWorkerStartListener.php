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
use Swoole\Coroutine;
use Swoole\Process;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use TgkwAdc\Helper\Log\LogHelper;
use TgkwAdc\Helper\OrgPermissionHelper;
use TgkwAdc\Helper\SystemPermissionHelper;
use TgkwAdc\Helper\XxlJobTaskHelper;
use TgkwAdc\JsonRpc\Public\SystemServiceInterface;
use TgkwAdc\JsonRpc\User\UserServiceInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[Listener(priority: 0)]
class MainWorkerStartListener implements ListenerInterface
{

    /**
     * The output interface implementation.
     *
     * @var OutputInterface
     */
    protected $output;
    public function listen(): array
    {
        return [
            MainWorkerStart::class,
        ];
    }

    public function process(object $event): void
    {
        $this->output = new ConsoleOutput();

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
        $this->output->writeln('<info>[启动] 校验 MQ Consumer/Producer 命名规范...</info>');
        $this->validateMqAnnotations();

        // 初始化打开 xxl-job
        $this->output->writeln('<info>[启动] 初始化 xxl-job...</info>');
        $this->initXxlJob();

        LogHelper::info('开始同步菜单');
        $this->output->writeln('<info>[启动] 开始同步菜单...</info>');

        //        // 同步菜单 - 等待配置从 Nacos 同步完成
        //        $systemConfig = $this->waitForSystemConfig();
        //        if ($systemConfig === null) {
        //            LogHelper::error('无法获取 systemConfig 配置，跳过菜单同步');
        //            return;
        //        }
        //
        //        if (! isset($systemConfig['needAddMenuSrv']) || ! is_array($systemConfig['needAddMenuSrv'])) {
        //            LogHelper::error('systemConfig 配置格式错误：缺少 needAddMenuSrv 字段或格式不正确，跳过菜单同步');
        //            return;
        //        }

        $appName = env('APP_NAME');
        //        if ($systemConfig['needAddMenuSrv'] && ! in_array($appName, $systemConfig['needAddMenuSrv'], true)) {
        //            LogHelper::info("当前服务 [{$appName}] 不在需要同步菜单的服务列表中，跳过菜单同步");
        //            return;
        //        }

        $this->syncMenus($appName);

        $elapsed = number_format(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 2);
        LogHelper::info('启动完成！（耗时：' . $elapsed . 's）');
        $this->output->writeln("<info>[启动] 启动完成！（耗时：{$elapsed}s）</info>");
    }


    /**
     * 同步菜单到用户服务和系统服务.
     */
    private function syncMenus(string $appName): void
    {
        try {
            if ($appName === 'idp'){
                //IDP也用到了此包，其非微服务有内部同步机制，不需要同步菜单
                $this->output->writeln('<comment>[菜单] 当前服务为 idp，跳过菜单同步</comment>');
                return;
            }

            // 同步租户菜单
            $orgMenuData = OrgPermissionHelper::build();
            LogHelper::info('租户菜单数据:' . count($orgMenuData['annotations']) . '条', []);
            LogHelper::info('租户菜单数据:' . count($orgMenuData['annotations']) . '条', [$orgMenuData], 'org_menu_data');
            $this->output->writeln('<info>[菜单] 租户菜单数据：' . count($orgMenuData['annotations']) . ' 条</info>');

            if (! empty($orgMenuData['annotations'])) {
                $this->output->writeln('<info>[菜单] 校验租户菜单 accessCode / frontRouteAlias...</info>');
                $this->validateMenuAccessCodes($orgMenuData['annotations'], 'OrgPermission');
                $this->validateMenuFrontRouteAlias($orgMenuData['annotations'], 'SystemPermission');
            }

            if ($appName === 'user' && class_exists('\App\JsonRpc\Provider\UserService')) {
                $userService = make('\App\JsonRpc\Provider\UserService');
            } else {
                $userService = ApplicationContext::getContainer()->get(UserServiceInterface::class);
            }
            if (! empty($orgMenuData)) {
                $res = $userService->addMenu($orgMenuData);
                LogHelper::info('租户菜单同步完成');
                LogHelper::info('租户菜单同步完成,收到的结果:', [$res]);
                $this->output->writeln('<info>[菜单] 租户菜单同步完成 ✅</info>');
            } else {
                LogHelper::info('租户菜单为空，跳过同步');
                $this->output->writeln('<comment>[菜单] 租户菜单为空，跳过同步</comment>');
            }

            // 同步系统总后台菜单
            $sysMenuData = SystemPermissionHelper::build();
            LogHelper::info('系统菜单数据:' . count($orgMenuData['annotations']) . '条', []);
            LogHelper::info('系统菜单数据:' . count($orgMenuData['annotations']) . '条', [$sysMenuData], 'sys_menu_data');
            $this->output->writeln('<info>[菜单] 系统菜单数据：' . count($sysMenuData['annotations'] ?? []) . ' 条</info>');

            if (! empty($sysMenuData)) {
                $this->output->writeln('<info>[菜单] 校验系统菜单 accessCode / frontRouteAlias...</info>');
                $this->validateMenuAccessCodes($orgMenuData['annotations'], 'SystemPermission');
                $this->validateMenuFrontRouteAlias($orgMenuData['annotations'], 'SystemPermission');
            }

            if ($appName === 'public' && class_exists('\App\JsonRpc\Provider\SystemService')) {
                $systemService = make('\App\JsonRpc\Provider\SystemService');
            } else {
                $systemService = ApplicationContext::getContainer()->get(SystemServiceInterface::class);
            }
            if (! empty($sysMenuData)) {
                $res = $systemService->addMenu($sysMenuData);
                LogHelper::info('系统菜单同步完成,收到的结果:', [$res]);
                $this->output->writeln('<info>[菜单] 系统菜单同步完成 ✅</info>');
            } else {
                LogHelper::info('系统菜单为空，跳过同步');
                $this->output->writeln('<comment>[菜单] 系统菜单为空，跳过同步</comment>');
            }
        } catch (Exception $e) {
            LogHelper::error('菜单同步失败: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            $this->output->writeln('<error>[菜单] 菜单同步失败：' . $e->getMessage() . '</error>');
            // 不抛出异常，避免影响服务启动
        }
    }

    /**
     * 校验 MQ Consumer 和 Producer 的 Queue/Exchange 命名规范.
     */
    private function validateMqAnnotations(): void
    {
        if (! env('AMQP_USER') || ! env('AMQP_PASSWORD') || ! env('APP_NAME')) {
            $this->output->writeln('<comment>[MQ] 未配置 AMQP，跳过 MQ 命名规范校验</comment>');
            return;
        }

        $currentServiceName = env('APP_NAME');
        $localConsumerExchanges = []; // 记录当前服务Consumer使用的Exchange（本服务消费的Exchange）

        // 1. Consumer校验：Queue和Exchange必须以当前服务名开头（或系统级），避免消费冲突
        $consumerClasses = AnnotationCollector::getClassesByAnnotation(Consumer::class);
        $this->output->writeln('<info>[MQ] 校验 Consumer：' . count($consumerClasses ?: []) . ' 个</info>');
        if (! empty($consumerClasses)) {
            foreach ($consumerClasses as $item) {
                // 校验Queue：必须以服务名开头（系统级可例外，可选）
                if (! empty($item->queue) && stripos($item->queue, $currentServiceName) !== 0 && stripos($item->queue, 'system') !== 0) {
                    $errorMsg = "❌ MQ Consumer 校验失败：Queue[{$item->queue}] 必须以服务名[{$currentServiceName}]或系统前缀[system]开头";
                    LogHelper::error($errorMsg);
                    echo $errorMsg . PHP_EOL;
                    $this->output->writeln('<error>' . $errorMsg . '</error>');
                    Process::kill((int) file_get_contents(\Hyperf\Config\config('server.settings.pid_file')));
                    return;
                }

                // 校验Exchange：必须以服务名开头（系统级可例外）
                if (! empty($item->exchange) && stripos($item->exchange, $currentServiceName) !== 0 && stripos($item->exchange, 'system') !== 0) {
                    $errorMsg = "❌ MQ Consumer 校验失败：Exchange[{$item->exchange}] 必须以服务名[{$currentServiceName}]或系统前缀[system]开头";
                    LogHelper::error($errorMsg);
                    echo $errorMsg . PHP_EOL;
                    $this->output->writeln('<error>' . $errorMsg . '</error>');
                    Process::kill((int) file_get_contents(\Hyperf\Config\config('server.settings.pid_file')));
                    return;
                }

                $localConsumerExchanges[] = $item->exchange; // 记录本服务消费的Exchange
            }
        }
        $this->output->writeln('<info>[MQ] Consumer 校验通过 ✅</info>');

        // 2. Producer校验：仅限制Exchange命名规范（非空、不非法），不限制归属（允许投递到其他服务的Exchange）
        $producerClasses = AnnotationCollector::getClassesByAnnotation(Producer::class);
        $this->output->writeln('<info>[MQ] 校验 Producer：' . count($producerClasses ?: []) . ' 个</info>');
        if (! empty($producerClasses)) {
            foreach ($producerClasses as $item) {
                // 基础校验：Exchange不能为空（避免无效配置）
                if (empty($item->exchange)) {
                    $errorMsg = '❌ MQ Producer 校验失败：Exchange 不能为空';
                    LogHelper::error($errorMsg);
                    echo $errorMsg . PHP_EOL;
                    $this->output->writeln('<error>' . $errorMsg . '</error>');
                    Process::kill((int) file_get_contents(\Hyperf\Config\config('server.settings.pid_file')));
                    return;
                }

                // Exchange必须包含服务名（目标服务名或当前服务名），避免无意义命名
                if (! preg_match('/^[a-zA-Z0-9.-]+$/', $item->exchange) || substr_count($item->exchange, '.') < 1) {
                    $errorMsg = "❌ MQ Producer 校验失败：Exchange[{$item->exchange}] 命名不规范！" . PHP_EOL
                        . '允许字符：字母（a-z/A-Z）、数字（0-9）、点（.）、连字符（-）' . PHP_EOL
                        . '建议格式：[服务名].[功能模块].[操作]（如 orderService.order.createOrder）';
                    LogHelper::error($errorMsg);
                    echo $errorMsg . PHP_EOL;
                    $this->output->writeln('<error>' . $errorMsg . '</error>');
                    Process::kill((int) file_get_contents(\Hyperf\Config\config('server.settings.pid_file')));
                    return;
                }

                // 保留原逻辑：若Producer投递的Exchange是本服务Consumer正在使用的，需符合Consumer的规则（避免本服务Exchange命名冲突）
                if (in_array($item->exchange, $localConsumerExchanges) && stripos($item->exchange, $currentServiceName) !== 0 && stripos($item->exchange, 'system') !== 0) {
                    $errorMsg = "❌ MQ Producer 校验失败：Exchange[{$item->exchange}] 是本服务Consumer使用的，必须以服务名[{$currentServiceName}]或系统前缀[system]开头";
                    LogHelper::error($errorMsg);
                    echo $errorMsg . PHP_EOL;
                    $this->output->writeln('<error>' . $errorMsg . '</error>');
                    Process::kill((int) file_get_contents(\Hyperf\Config\config('server.settings.pid_file')));
                    return;
                }
            }
        }
        $this->output->writeln('<info>[MQ] Producer 校验通过 ✅</info>');
    }

    /**
     * 初始化 xxl-job 任务.
     */
    private function initXxlJob(): void
    {
        LogHelper::info('xxl-job-task init now');
        if (env('XXL_JOB_ENABLE') === true) {
            LogHelper::info('xxl-job is enable! ✅ ');
            $this->output->writeln('<info>[xxl-job] 已启用，开始注册任务...</info>');
            $XxlJobTaskHelper = new XxlJobTaskHelper();
            $XxlJobTaskHelper->build(true);
            $this->output->writeln('<info>[xxl-job] 任务注册完成 ✅</info>');
        } else {
            $this->output->writeln('<comment>[xxl-job] 未启用，跳过</comment>');
        }
    }

    /**
     * 校验菜单注解中的 accessCode 和 parentAccessCode 格式.
     * 格式要求：全小写，多单词用 - 连接，层级用 : 分隔（如 system:business-rules:recycle-rule）.
     *
     * @param array $annotations 菜单注解列表
     * @param string $type 注解类型（OrgPermission / SystemPermission）
     */
    private function validateMenuAccessCodes(array $annotations, string $type): void
    {
        // 格式：全小写，段内用-连词，段间用:分隔
        $pattern = '/^[a-z][a-z0-9]*(-[a-z][a-z0-9]*)*(:[a-z][a-z0-9]*(-[a-z][a-z0-9]*)*)*$/';

        $accessCodes = [];
        $parentRefs = [];

        foreach ($annotations as $item) {
            $annotation = $item['annotation'] ?? null;
            if ($annotation === null) {
                continue;
            }

            $accessCode = $annotation->accessCode ?? '';
            $parentAccessCode = $annotation->parentAccessCode ?? '';
            $action = $item['action'] ?? 'unknown';

            if (! empty($accessCode) && ! preg_match($pattern, $accessCode)) {
                $errorMsg = "❌ {$type} accessCode 格式校验失败：action [{$action}] accessCode [{$accessCode}] 不符合规范！" . PHP_EOL
                    . '格式要求：全小写字母，多单词用 - 连接，层级用 : 分隔（如 system:business-rules:recycle-rule）';
                LogHelper::error($errorMsg);
                echo $errorMsg . PHP_EOL;
                $this->output->writeln('<error>' . $errorMsg . '</error>');
                Process::kill((int) file_get_contents(\Hyperf\Config\config('server.settings.pid_file')));
                return;
            }

            if (! empty($parentAccessCode) && ! preg_match($pattern, $parentAccessCode)) {
                $errorMsg = "❌ {$type} parentAccessCode 格式校验失败：action [{$action}] parentAccessCode [{$parentAccessCode}] 不符合规范！" . PHP_EOL
                    . '格式要求：全小写字母，多单词用 - 连接，层级用 : 分隔（如 system:business-rules:recycle-rule）';
                LogHelper::error($errorMsg);
                echo $errorMsg . PHP_EOL;
                $this->output->writeln('<error>' . $errorMsg . '</error>');
            }

            if (! empty($accessCode)) {
                $accessCodes[$accessCode] = true;
            }
            if (! empty($parentAccessCode)) {
                $parentRefs[$parentAccessCode] = $action;
            }
        }

        // 校验：所有 parentAccessCode 必须存在对应的 accessCode，避免找不到父级
        foreach ($parentRefs as $parentAccessCode => $action) {
            if (! isset($accessCodes[$parentAccessCode])) {
                $errorMsg = "❌ {$type} parentAccessCode 引用校验失败：action [{$action}] parentAccessCode [{$parentAccessCode}] 找不到对应的 accessCode！" . PHP_EOL
                    . '请确认存在一个 accessCode 与该 parentAccessCode 完全一致。';
                LogHelper::error($errorMsg);
                echo $errorMsg . PHP_EOL;
                $this->output->writeln('<error>' . $errorMsg . '</error>');
                Process::kill((int) file_get_contents(\Hyperf\Config\config('server.settings.pid_file')));
                return;
            }
        }
    }

    private function validateMenuFrontRouteAlias(array $annotations, string $type): void
    {
        $pattern = '/^[a-z][a-z0-9]*(-[a-z][a-z0-9]*)*(.[a-z][a-z0-9]*(-[a-z][a-z0-9]*)*)*$/';

        foreach ($annotations as $item) {
            $annotation = $item['annotation'] ?? null;
            if ($annotation === null) {
                continue;
            }

            $frontRouteAlias = $annotation->frontRouteAlias ?? '';
            $action = $item['action'] ?? 'unknown';

            if (! empty($frontRouteAlias) && ! preg_match($pattern, $frontRouteAlias)) {
                $errorMsg = "❌ {$type} frontRouteAlias 格式校验失败：action [{$action}] frontRouteAlias [{$frontRouteAlias}] 不符合规范！" . PHP_EOL
                    . '格式要求：全小写字母，多单词用 - 连接，层级用 . 分隔（如 system.business-rules.recycle-rule）';
                LogHelper::error($errorMsg);
                echo $errorMsg . PHP_EOL;
                $this->output->writeln('<error>' . $errorMsg . '</error>');
                Process::kill((int) file_get_contents(\Hyperf\Config\config('server.settings.pid_file')));
                return;
            }
        }
    }
}

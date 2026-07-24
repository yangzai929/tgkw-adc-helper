<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Listener;

use Composer\InstalledVersions;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BeforeMainServerStart;
use Swoole\Process;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * 服务启动时校验指定 Composer 包版本（不在执行 Command 时触发）.
 */
class PackageVersionCheckListener implements ListenerInterface
{
    /**
     * The output interface implementation.
     *
     * @var OutputInterface
     */
    protected $output;

    public function __construct(
        private ConfigInterface $config
    ) {
    }

    public function listen(): array
    {
        return [BeforeMainServerStart::class];
    }

    public function process(object $event): void
    {
        $this->output = new ConsoleOutput();

        $required = $this->config->get('package_versions', []);
        $this->output->writeln('<info>[包版本] 开始校验 Composer 包版本：' . count($required) . ' 个</info>');
        foreach ($required as $package => $constraint) {
            $installed = InstalledVersions::getVersion($package);
            if ($installed === null) {
                $errorMsg = "[包版本] 校验失败：包 [{$package}] 未安装";
                $this->writeError($errorMsg);
                Process::kill((int) file_get_contents(\Hyperf\Config\config('server.settings.pid_file')));
                return;
            }
            if (! $this->satisfies($installed, $constraint)) {
                $errorMsg = "[包版本] 校验失败：包 [{$package}] 版本不满足当前服务最新代码要求" . PHP_EOL
                    . "  已安装：{$installed}" . PHP_EOL
                    . "  需要：{$constraint}" . PHP_EOL
                    . '  请先更新 tgkw-adc/helper 到最新版本';
                $this->writeError($errorMsg);
                Process::kill((int) file_get_contents(\Hyperf\Config\config('server.settings.pid_file')));
                return;
            }
            $this->output->writeln("<info>[包版本] {$package} {$installed} 满足 {$constraint}</info>");
        }
        $this->output->writeln('<info>[包版本] 校验通过</info>');
    }

    private function writeError(string $message): void
    {
        foreach (preg_split("/\r\n|\n|\r/", $message) ?: [$message] as $line) {
            $this->output->writeln('<fg=red;options=bold>' . $line . '</>');
        }
    }

    private function satisfies(string $installed, string $constraint): bool
    {
        if (str_starts_with($constraint, '>=')) {
            return version_compare($installed, substr($constraint, 2), '>=');
        }
        if (str_starts_with($constraint, '>')) {
            return version_compare($installed, substr($constraint, 1), '>');
        }
        if (str_starts_with($constraint, '^')) {
            $min = substr($constraint, 1);
            return version_compare($installed, $min, '>=') && $this->compatibleWith($installed, $min);
        }
        if (str_starts_with($constraint, '~')) {
            $min = substr($constraint, 1);
            return version_compare($installed, $min, '>=') && $this->tildeCompatible($installed, $min);
        }
        return version_compare($installed, $constraint, '=');
    }

    private function compatibleWith(string $installed, string $min): bool
    {
        $parts = explode('.', $min);
        $major = (int) ($parts[0] ?? 0);
        $nextMajor = $major + 1 . '.0.0';
        return version_compare($installed, $nextMajor, '<');
    }

    private function tildeCompatible(string $installed, string $min): bool
    {
        $parts = explode('.', $min);
        $minor = (int) ($parts[1] ?? 0);
        $max = ($parts[0] ?? '0') . '.' . ($minor + 1) . '.0';
        return version_compare($installed, $max, '<');
    }
}

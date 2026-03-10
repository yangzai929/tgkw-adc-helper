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
use Hyperf\Framework\Event\BootApplication;
use RuntimeException;

/**
 * 应用启动时校验指定 Composer 包版本.
 */
class PackageVersionCheckListener implements ListenerInterface
{
    public function __construct(
        private ConfigInterface $config
    ) {
    }

    public function listen(): array
    {
        return [BootApplication::class];
    }

    public function process(object $event): void
    {
        $required = $this->config->get('package_versions', []);
        foreach ($required as $package => $constraint) {
            $installed = InstalledVersions::getVersion($package);
            if ($installed === null) {
                throw new RuntimeException("包 [{$package}] 未安装");
            }
            if (! $this->satisfies($installed, $constraint)) {
                throw new RuntimeException(
                    "包 [{$package}] 版本不满足当前服务最新代码要求: 已安装 {$installed}, 需要 {$constraint}，请先更新tgkw-adc/helper到最新版本"
                );
            }
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

<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Controller;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionEnum;
use TgkwAdc\Annotation\EnumCode;
use TgkwAdc\Annotation\EnumCodeInterface;
use TgkwAdc\Annotation\EnumCodePrefix;
use TgkwAdc\Helper\ApiResponseHelper;
use Throwable;

#[Controller]

class CodeQueryController extends AbstractController
{


    /**
     * 获取错误码目录路径.
     * 子类可以重写此方法来自定义路径.
     */
    protected function getCodeDir(): string
    {
        return BASE_PATH . '/app/Constants/Code';
    }

    /**
     * 获取错误码列表.
     */
    #[GetMapping(path: '/error-codes')]
    public function index()
    {
        $codeDir = $this->getCodeDir();
        $codes = $this->scanCodeFiles($codeDir);

        return ApiResponseHelper::debug($codes);
    }


    /**
     * 扫描错误码文件.
     */
    protected function scanCodeFiles(string $dir): array
    {
        $result = [];
        $files = $this->getPhpFiles($dir);

        foreach ($files as $file) {
            $className = $this->getClassNameFromFile($file);
            if (! $className) {
                continue;
            }

            try {
                if (! enum_exists($className)) {
                    continue;
                }

                $reflection = new ReflectionClass($className);

                // 检查是否实现了 EnumCodeInterface
                if (! $reflection->implementsInterface(EnumCodeInterface::class)) {
                    continue;
                }

                // 检查是否是枚举类型
                if (! $reflection->isEnum()) {
                    continue;
                }

                $enumReflection = new ReflectionEnum($className);

                // 获取 EnumCodePrefix 注解
                $prefixAttributes = $reflection->getAttributes(EnumCodePrefix::class);
                $prefixCode = null;
                $prefixInfo = '';

                if (! empty($prefixAttributes)) {
                    $prefixAttr = $prefixAttributes[0]->newInstance();
                    $prefixCode = $prefixAttr->prefixCode ?? null;
                    $prefixInfo = $prefixAttr->info ?? '';
                }

                $enumData = [
                    'prefix_code' => $prefixCode,
                    'prefix_info' => $prefixInfo,
                    'codes' => [],
                ];

                // 获取所有枚举 case
                $cases = $enumReflection->getCases();
                foreach ($cases as $case) {
                    // 获取枚举值（对于有 backing value 的枚举，如 enum X: int）
                    $caseInstance = $case->getValue();
                    $caseValue = $caseInstance instanceof BackedEnum ? $caseInstance->value : null;

                    // 获取 EnumCode 注解
                    $codeAttributes = $case->getAttributes(EnumCode::class);
                    $msg = '';

                    if (! empty($codeAttributes)) {
                        $codeAttr = $codeAttributes[0]->newInstance();
                        $msg = $codeAttr->msg ?? '';
                    }

                    // 计算完整错误码
                    $fullCode = $prefixCode ? ($prefixCode * 1000 + $caseValue) : $caseValue;

                    $enumData['codes'][] = [
                        'code' => $fullCode,
                        'message' => $msg,
                    ];
                }

                $result[] = $enumData;
            } catch (Throwable $e) {
                // 忽略无法解析的文件
                continue;
            }
        }

        // 按照 prefix_code 排序
        usort($result, function ($a, $b) {
            $prefixCodeA = $a['prefix_code'] ?? 0;
            $prefixCodeB = $b['prefix_code'] ?? 0;
            return $prefixCodeA <=> $prefixCodeB;
        });

        return $result;
    }

    /**
     * 获取目录下所有 PHP 文件.
     */
    protected function getPhpFiles(string $dir): array
    {
        $files = [];

        if (! is_dir($dir)) {
            return $files;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * 从文件路径获取类名.
     */
    protected function getClassNameFromFile(string $filePath): ?string
    {
        $content = file_get_contents($filePath);
        if (! $content) {
            return null;
        }

        // 提取命名空间
        if (preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatch)) {
            $namespace = $namespaceMatch[1];
        } else {
            return null;
        }

        // 提取类名或枚举名
        if (preg_match('/\b(enum|class)\s+(\w+)/', $content, $classMatch)) {
            $className = $classMatch[2];
            return $namespace . '\\' . $className;
        }

        return null;
    }
}

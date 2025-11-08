<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Helper;

use Hyperf\Di\Annotation\AnnotationCollector;
use TgkwAdc\Annotation\OrgPermission;

class OrgPermissionHelper
{
    /**
     * 收集并整理所有 OrgPermission 注解信息.
     * @return array 结构化的权限注解列表
     */
    public static function build(): array
    {
        // 收集类级别注解
        $classAnnotations = self::collectClassAnnotations();
        // 收集方法级别注解
        $methodAnnotations = self::collectMethodAnnotations();

        // 合并并去重
        $merged = array_merge($classAnnotations, $methodAnnotations);
        return ['micro' => env('APP_NAME'), 'annotations' => self::deduplicate($merged), 'version' => time()];
    }

    /**
     * 收集类级别 OrgPermission 注解.
     */
    private static function collectClassAnnotations(): array
    {
        $result = [];
        // 获取类注解映射（类名 => 注解实例）
        $annotations = AnnotationCollector::getClassesByAnnotation(OrgPermission::class);

        foreach ($annotations as $className => $annotation) {
            if (! $annotation instanceof OrgPermission) {
                continue; // 过滤非目标注解
            }
            $result[] = [
                'type' => 'class',
                'class' => $className,
                'method' => null,
                'action' => $className . '@',
                'annotation' => $annotation,
            ];
        }

        return $result;
    }

    /**
     * 收集方法级别 OrgPermission 注解.
     */
    private static function collectMethodAnnotations(): array
    {
        $result = [];
        // 获取方法注解列表：[['class' => '', 'method' => '', 'annotation' => ...], ...]
        $methodAnnotations = AnnotationCollector::getMethodsByAnnotation(OrgPermission::class);

        foreach ($methodAnnotations as $item) {
            $annotation = $item['annotation'] ?? null;
            if (! $annotation instanceof OrgPermission) {
                continue; // 过滤无效注解
            }

            $result[] = [
                'type' => 'method',
                'class' => $item['class'],
                'method' => $item['method'],
                'action' => $item['class'] . '@' . $item['method'],
                'annotation' => $annotation,
            ];
        }

        return $result;
    }

    /**
     * 对注解列表去重（按 类+方法+权限标识 组合）.
     */
    private static function deduplicate(array $annotations): array
    {
        $unique = [];
        foreach ($annotations as $item) {
            // 生成唯一键：类名+方法名+权限标识（确保组合唯一）
            $key = $item['action'];
            if (! isset($unique[$key])) {
                $unique[$key] = $item;
            }
        }
        return array_values($unique);
    }
}

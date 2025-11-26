<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Utils;

use Exception;
use Hyperf\Di\Annotation\Inject;
use Ip2Region;
use Psr\Http\Message\ServerRequestInterface;
use TgkwAdc\IP2Location\Database;

class IpTool
{
    /**
     * 常见的反向代理/CDN 透传 IP 的请求头（优先级从高到低）.
     */
    private const IP_HEADERS = [
        'X-Forwarded-For',
        'X-Real-IP',
        'Proxy-Client-IP',
        'WL-Proxy-Client-IP',
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
    ];

    /**
     * IPv4 内网地址段（含子网掩码说明）.
     */
    private const IPv4_INTERNAL_RANGES = [
        ['start' => '10.0.0.0', 'end' => '10.255.255.255', 'desc' => 'A类内网'],
        ['start' => '172.16.0.0', 'end' => '172.31.255.255', 'desc' => 'B类内网'],
        ['start' => '192.168.0.0', 'end' => '192.168.255.255', 'desc' => 'C类内网'],
        ['start' => '127.0.0.0', 'end' => '127.255.255.255', 'desc' => '本地回环'],
        ['start' => '169.254.0.0', 'end' => '169.254.255.255', 'desc' => '链路本地地址'],
    ];

    #[Inject]
    protected Ip2Region $ip2region;

    /**
     * 获取真实客户端 IP（处理反向代理/CDN）.
     *
     * @param ServerRequestInterface $request 请求对象
     * @return string 纯净的IP地址（默认0.0.0.0）
     */
    public static function getRealIp(ServerRequestInterface $request): string
    {
        // 1. 优先从反向代理头中获取
        foreach (self::IP_HEADERS as $header) {
            $ipLine = $request->getHeaderLine($header);
            if (empty($ipLine) || $ipLine === 'unknown') {
                continue;
            }

            // 分割多个IP（逗号分隔），过滤空值并去重
            $ipList = array_filter(array_map('trim', explode(',', $ipLine)));
            $ipList = array_unique($ipList);

            // 优先返回第一个非内网IP
            foreach ($ipList as $ip) {
                if (self::isValidIp($ip) && ! self::isInternalIp($ip)) {
                    return $ip;
                }
            }

            // 若全是内网IP，返回第一个有效IP
            foreach ($ipList as $ip) {
                if (self::isValidIp($ip)) {
                    return $ip;
                }
            }
        }

        // 2. 直接从服务器参数获取（无反向代理场景）
        $remoteIp = $request->getServerParams()['remote_addr'] ?? '';
        if (self::isValidIp($remoteIp)) {
            return $remoteIp;
        }

        // 3. 默认返回（无效IP场景）
        return '0.0.0.0';
    }

    /**
     * 验证IP地址有效性（支持IPv4/IPv6）.
     *
     * @param string $ip IP地址
     * @return bool 是否有效
     */
    public static function isValidIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * 判断 IP 是否为内网地址（同时支持 IPv4 / IPv6）.
     */
    public static function isInternalIp(string $ip): bool
    {
        // 先验证IP有效性
        if (! self::isValidIp($ip)) {
            return false;
        }

        // IPv4 内网判断
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipLong = ip2long($ip);
            if ($ipLong === false) {
                return false;
            }

            foreach (self::IPv4_INTERNAL_RANGES as $range) {
                $startLong = ip2long($range['start']);
                $endLong = ip2long($range['end']);
                if ($ipLong >= $startLong && $ipLong <= $endLong) {
                    return true;
                }
            }

            return false;
        }

        // IPv6 内网判断
        $packedIp = inet_pton($ip);
        if ($packedIp === false) {
            return false;
        }

        // ::1 本地回环
        if ($packedIp === inet_pton('::1')) {
            return true;
        }

        $firstByte = ord($packedIp[0]);
        $secondByte = ord($packedIp[1]);

        // fc00::/7 唯一本地地址（ULA）
        if (($firstByte & 0xFE) === 0xFC) {
            return true;
        }

        // fe80::/10 链路本地地址（LLA）
        if ($firstByte === 0xFE && ($secondByte & 0xC0) === 0x80) {
            return true;
        }

        return false;
    }

    /**
     * 本地数据库解析 IP 所在地（IP2Location LITE DB5）.
     */
    public function getIpLocation(string $ip): string
    {
        // 处理内网 IP
        if (self::isInternalIp($ip)) {
            return '内网';
        }

        // 2. 验证IP有效性
        if (! self::isValidIp($ip)) {
            return '未知';
        }

        try {
            $res = ip2region($ip);
            if ($res) {
                return $res;
            }
            // 初始化数据库连接（FILE_IO 模式，适合小数据库；MEMORY_CACHE 模式更高效，需更多内存）
            $db = new Database();
            $record = $db->lookup($ip, Database::ALL); // 解析所有信息

            return ($record['countryName'] ?? '未知') . '|' . ($record['regionName'] ?? '未知') . '|' . ($record['cityName'] ?? '未知');
        } catch (Exception $e) {
            return '未知';
        }
    }
}

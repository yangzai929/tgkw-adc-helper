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
use Hyperf\HttpServer\Contract\RequestInterface;
use TgkwAdc\IP2Location\Database;
use TgkwAdc\Ip2region\Ip2region;

class IpTool
{
    #[Inject]
    protected Ip2region $ip2region;

    /**
     * 第一步：获取真实客户端 IP（处理反向代理/CDN）.
     */
    public function getRealIp(RequestInterface $request): string
    {
        // 常见的反向代理/CDN 透传 IP 的请求头
        $ipHeaders = [
            'X-Forwarded-For',
            'X-Real-IP',
            'Proxy-Client-IP',
            'WL-Proxy-Client-IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
        ];

        foreach ($ipHeaders as $header) {
            $ip = $request->getHeaderLine($header);
            if ($ip && $ip !== 'unknown') {
                // X-Forwarded-For 可能包含多个 IP（逗号分隔），取第一个非内网 IP
                $ipList = explode(',', $ip);
                foreach ($ipList as $realIp) {
                    $realIp = trim($realIp);
                    // 排除内网 IP（10.0.0.0/8、172.16.0.0/12、192.168.0.0/16）
                    if (! $this->isInternalIp($realIp)) {
                        return $realIp;
                    }
                }
                return trim($ipList[0]); // 若全是内网 IP，取第一个
            }
        }

        // 若没有反向代理，直接获取 REMOTE_ADDR
        return $request->getServerParams()['remote_addr'] ?? '0.0.0.0';
    }

    /**
     * 判断 IP 是否为内网地址（同时支持 IPv4 / IPv6）.
     */
    public function isInternalIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipLong = ip2long($ip);
            if ($ipLong === false) {
                return false;
            }

            $ranges = [
                ['start' => ip2long('10.0.0.0'), 'end' => ip2long('10.255.255.255')],
                ['start' => ip2long('172.16.0.0'), 'end' => ip2long('172.31.255.255')],
                ['start' => ip2long('192.168.0.0'), 'end' => ip2long('192.168.255.255')],
                ['start' => ip2long('127.0.0.0'), 'end' => ip2long('127.255.255.255')], // loopback
                ['start' => ip2long('169.254.0.0'), 'end' => ip2long('169.254.255.255')], // link-local
            ];

            foreach ($ranges as $range) {
                if ($ipLong >= $range['start'] && $ipLong <= $range['end']) {
                    return true;
                }
            }

            return false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = inet_pton($ip);
            if ($packed === false) {
                return false;
            }

            // ::1 loopback
            if ($packed === inet_pton('::1')) {
                return true;
            }

            $first = ord($packed[0]);
            $second = ord($packed[1]);

            // fc00::/7 Unique Local Addresses
            if (($first & 0xFE) === 0xFC) {
                return true;
            }

            // fe80::/10 Link-local
            if ($first === 0xFE && ($second & 0xC0) === 0x80) {
                return true;
            }

            return false;
        }

        return false;
    }

    /**
     * 本地数据库解析 IP 所在地（IP2Location LITE DB5）.
     */
    public function getIpLocation(string $ip): string
    {
        // 处理内网 IP
        if ($this->isInternalIp($ip)) {
            return '内网';
        }

        $res = $this->ip2region->memorySearch($ip);
        if ($res) {
            return $res['region'];
        }

        try {
            // 初始化数据库连接（FILE_IO 模式，适合小数据库；MEMORY_CACHE 模式更高效，需更多内存）
            $db = new Database();
            $record = $db->lookup($ip, Database::ALL); // 解析所有信息

            return ($record['countryName'] ?? '未知') . '|' . ($record['regionName'] ?? '未知') . '|' . ($record['cityName'] ?? '未知');
        } catch (Exception $e) {
            return '未知';
        }
    }
}

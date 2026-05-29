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
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;

/**
 * 数脉数据API工具类
 * 封装了调用数脉数据企业模糊搜索接口的通用方法.
 */
class ShumaiData
{
    private string $baseUrl = 'https://businessfuzzy.shumaidata.com';

    private string $appCode;

    private Client $client;

    /**
     * 构造函数，初始化AppCode.
     * @param string $appCode 从数脉数据平台获取的AppCode
     * @throws InvalidArgumentException 如果AppCode为空则抛出异常
     */
    public function __construct(string $appCode)
    {
        if (empty($appCode)) {
            throw new InvalidArgumentException('数脉数据AppCode不能为空');
        }
        $this->appCode = $appCode;
        $this->client = new Client([
            'timeout' => 30,
            'verify' => false,
        ]);
    }

    /**
     * 企业模糊搜索.
     * @param string $keyword 搜索关键词（企业名称）
     * @return array 解析后的搜索结果数组
     * @throws Exception 调用失败时抛出异常
     */
    public function query(string $keyword): array
    {
        if (empty($keyword)) {
            throw new InvalidArgumentException('搜索关键词不能为空');
        }

        $url = $this->buildRequestUrl('/getbusinessfuzzy', ['keyword' => $keyword]);
        $response = $this->sendRequest($url);

        $result = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('API返回数据格式错误: ' . json_last_error_msg());
        }

        return $result;
    }

    /**
     * 设置自定义的基础URL（如测试环境）.
     * @param string $baseUrl 新的基础URL
     */
    public function setBaseUrl(string $baseUrl): void
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * 构建完整的请求URL.
     * @param string $apiPath API接口路径
     * @param array $params URL参数
     * @return string 完整的URL
     */
    private function buildRequestUrl(string $apiPath, array $params): string
    {
        $queryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        return $this->baseUrl . $apiPath . '?' . $queryString;
    }

    /**
     * 发送HTTP请求.
     * @param string $url 请求地址
     * @return string 响应内容
     * @throws Exception 请求失败时抛出异常
     */
    private function sendRequest(string $url): string
    {
        try {
            $response = $this->client->get($url, [
                'headers' => [
                    'Authorization' => 'APPCODE ' . $this->appCode,
                    'Content-Type' => 'application/json',
                ],
            ]);

            $httpCode = $response->getStatusCode();
            if ($httpCode < 200 || $httpCode >= 300) {
                $body = $response->getBody()->getContents();
                throw new Exception("API请求失败，HTTP状态码: {$httpCode}，响应内容: {$body}");
            }

            return $response->getBody()->getContents();
        } catch (GuzzleException $e) {
            throw new Exception('HTTP请求失败: ' . $e->getMessage(), 0, $e);
        }
    }
}
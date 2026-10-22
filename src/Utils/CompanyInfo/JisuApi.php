<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Utils\CompanyInfo;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;

/**
 * 极速数据-企业工商信息 API 工具类（阿里云市场）.
 *
 * 封装两个接口：
 * - query   企业工商信息精准查询  GET /enterprise/query
 *           （company / creditno / regno / orgno 任选其一，公司名为工商名称全匹配）
 * - search  企业模糊搜索          GET /enterprise/search（keyword 必填，page/pagesize 选填）
 *
 * 认证方式：APPCODE（请求头 Authorization: APPCODE {appCode}），
 * 在阿里云市场控制台获取（productCode=cmapi033475）。
 *
 * 成功返回结构（status 为字符串 "0" 表示成功，其余为失败）：
 * { "status": "0", "msg": "ok", "result": { "basic": { name, type, regcapital,
 *   regcapitalcn, scope, startdate, enddate, regorgan, legalperson, status,
 *   orgno, creditno, regno, regaddress, regcapitalcur, reccap, nicname,
 *   insurenum, ... } } }
 * 说明：精准查询(query)的企业字段位于 result.basic；search 的 result 为企业列表，
 * 单条结构同样可能被 basic 包裹，Provider 层已做兼容解包。
 */
class JisuApi
{
    private string $baseUrl = 'https://jisuqygsxx.market.alicloudapi.com';

    private string $appCode;

    private Client $client;

    /**
     * 构造函数，初始化 AppCode.
     * @param string $appCode 阿里云市场获取的 AppCode
     * @throws InvalidArgumentException 如果 AppCode 为空则抛出异常
     */
    public function __construct(string $appCode)
    {
        if (empty($appCode)) {
            throw new InvalidArgumentException('极速数据AppCode不能为空');
        }
        $this->appCode = $appCode;
        $this->client = new Client([
            'timeout' => 30,
            'verify' => false,
        ]);
    }

    /**
     * 企业工商信息精准查询.
     * @param array $params 查询条件，company（工商名称全匹配）/ creditno（统一信用代码）
     *                      / regno（注册号）/ orgno（组织机构代码）至少传一个
     * @return array 解析后的企业信息数组
     * @throws Exception 调用失败时抛出异常
     */
    public function query(array $params): array
    {
        $query = array_intersect_key($params, array_flip(['company', 'creditno', 'regno', 'orgno']));
        $query = array_filter($query, static fn ($value) => trim((string) $value) !== '');

        if (empty($query)) {
            throw new InvalidArgumentException('company/creditno/regno/orgno 至少传一个');
        }

        return $this->sendRequest('/enterprise/query', $query);
    }

    /**
     * 企业模糊搜索.
     * @param string $keyword 搜索关键词
     * @param int $page 页码
     * @param int $pageSize 每页条数（默认 10，最大 20）
     * @return array 解析后的搜索结果数组
     * @throws Exception 调用失败时抛出异常
     */
    public function search(string $keyword, int $page = 1, int $pageSize = 10): array
    {
        if (trim($keyword) === '') {
            throw new InvalidArgumentException('搜索关键词不能为空');
        }

        return $this->sendRequest('/enterprise/search', [
            'keyword' => $keyword,
            'page' => $page,
            'pagesize' => min($pageSize, 20),
        ]);
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
     * 发送HTTP请求并解析JSON响应.
     * @param string $apiPath 接口路径
     * @param array $params query 参数
     * @return array 解析后的响应数组
     * @throws Exception 请求失败时抛出异常
     */
    private function sendRequest(string $apiPath, array $params): array
    {
        $queryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $url = $this->baseUrl . $apiPath . '?' . $queryString;

        $body = '';
        $httpCode = 0;
        try {
            $response = $this->client->get($url, [
                'headers' => [
                    'Authorization' => 'APPCODE ' . $this->appCode,
                    'Content-Type' => 'application/json',
                ],
            ]);

            $httpCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();
        } catch (GuzzleException $e) {
            throw new Exception('HTTP请求失败: ' . $e->getMessage(), 0, $e);
        }

        // 部分网关在“查无数据”时返回空响应体，按无数据返回，由 Provider 统一处理
        if (trim($body) === '') {
            return ['status' => '-1', 'msg' => 'empty response', 'result' => null];
        }

        $result = json_decode($body, true);
        $isSuccessHttp = $httpCode >= 200 && $httpCode < 300;

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($result)) {
            // 2xx 但响应体为空/非 JSON/非数组，按无数据返回，避免触发返回值类型错误
            if ($isSuccessHttp) {
                return ['status' => '-1', 'msg' => 'invalid response', 'result' => null];
            }

            throw new Exception("API请求失败，HTTP状态码: {$httpCode}，响应内容: {$body}");
        }

        // 携带 status 字段的业务响应，无论 HTTP 状态码都交由 Provider 判定（查无数据时网关可能返回非 2xx）
        if (array_key_exists('status', $result)) {
            return $result;
        }

        if ($isSuccessHttp) {
            return ['status' => '-1', 'msg' => 'unexpected response', 'result' => null];
        }

        throw new Exception("API请求失败，HTTP状态码: {$httpCode}，响应内容: {$body}");
    }
}

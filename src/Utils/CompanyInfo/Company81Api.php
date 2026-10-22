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
 * 81api 企业数据 API 工具类（https://api.81api.com）.
 *
 * 封装两个接口：
 * - getCompanyBaseInfo       企业工商数据精准查询  GET /getCompanyBaseInfo/{企业名称或信用代码}/
 * - fuzzySuggestCompanyName  企业名称搜索建议查询  GET /fuzzySuggestCompanyName/{关键词}/
 *
 * 认证方式：APPCODE（请求头 Authorization: APPCODE {appCode}）。
 * 两个接口均支持 query 参数 isRaiseErrorCode（查无结果时是否抛出 404），
 * 固定传 0，避免查询不存在的企业/关键词时扣减次数。
 *
 * getCompanyBaseInfo 成功返回结构：
 * {
 *   "status": true,
 *   "data": {
 *     "name": "公司名称",
 *     "startDate": "成立日期",
 *     "registerCapital": "注册资本",
 *     "legalPersonName": "法定代表人",
 *     "socialEmployeeNum": "参保人数（可定制）",
 *     "registerData": {
 *       "status": "经营状态",
 *       "registerCapital": "注册资本",
 *       "actualCapital": "实缴注册资本（可定制）",
 *       "registerCapitalCurrency": "币种",
 *       "creditNo": "社会统一信用代码",
 *       "businessTerm": "营业期限",
 *       "belongOrg": "登记机关",
 *       "regType": "公司类型",
 *       "registerNo": "工商注册号",
 *       "orgNo": "组织机构代码",
 *       "address": "注册地址",
 *       "businessScope": "经营范围",
 *       "industry": "所属行业（可定制）"
 *     },
 *     "partnerData": {"total": 股东数量, "list": [...]},
 *     "changeRecordData": {"total": 变更数量, "list": [...]},
 *     "employeeData": {"total": 董事会成员数量, "list": [...]}
 *   }
 * }
 *
 * fuzzySuggestCompanyName 成功返回结构：
 * { "status": true, "data": { "list": [ {"companyname": "企业名称"}, ... ] } }
 */
class Company81Api
{
    private string $baseUrl = 'https://api.81api.com';

    private string $appCode;

    private Client $client;

    /**
     * 构造函数，初始化 AppCode.
     * @param string $appCode 从 81api 平台获取的 AppCode
     * @throws InvalidArgumentException 如果 AppCode 为空则抛出异常
     */
    public function __construct(string $appCode)
    {
        if (empty($appCode)) {
            throw new InvalidArgumentException('81api AppCode不能为空');
        }
        $this->appCode = $appCode;
        $this->client = new Client([
            'timeout' => 30,
            'verify' => false,
        ]);
    }

    /**
     * 企业工商数据精准查询.
     * @param string $companyNameOrCreditNo 企业名称或统一社会信用代码
     * @return array 解析后的企业信息数组
     * @throws Exception 调用失败时抛出异常
     */
    public function getCompanyBaseInfo(string $companyNameOrCreditNo): array
    {
        if (trim($companyNameOrCreditNo) === '') {
            throw new InvalidArgumentException('企业名称或信用代码不能为空');
        }

        return $this->sendRequest('/getCompanyBaseInfo/' . rawurlencode($companyNameOrCreditNo) . '/');
    }

    /**
     * 企业名称搜索建议查询（模糊）.
     * @param string $keyword 搜索关键词
     * @return array 解析后的搜索结果数组
     * @throws Exception 调用失败时抛出异常
     */
    public function fuzzySuggestCompanyName(string $keyword): array
    {
        if (trim($keyword) === '') {
            throw new InvalidArgumentException('搜索关键词不能为空');
        }

        return $this->sendRequest('/fuzzySuggestCompanyName/' . rawurlencode($keyword) . '/');
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
     * @param string $apiPath 接口路径（含路径参数）
     * @return array 解析后的响应数组
     * @throws Exception 请求失败时抛出异常
     */
    private function sendRequest(string $apiPath): array
    {
        // isRaiseErrorCode=0：查无结果时不抛 404，避免无效查询扣减次数
        $query = http_build_query(['isRaiseErrorCode' => 0], '', '&', PHP_QUERY_RFC3986);
        $url = $this->baseUrl . $apiPath . '?' . $query;

        try {
            $response = $this->client->get($url, [
                'headers' => [
                    'Authorization' => 'APPCODE ' . $this->appCode,
                    'Content-Type' => 'application/json',
                ],
            ]);

            $httpCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();
            if ($httpCode < 200 || $httpCode >= 300) {
                throw new Exception("API请求失败，HTTP状态码: {$httpCode}，响应内容: {$body}");
            }
        } catch (GuzzleException $e) {
            throw new Exception('HTTP请求失败: ' . $e->getMessage(), 0, $e);
        }

        $result = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('API返回数据格式错误: ' . json_last_error_msg());
        }

        return $result;
    }
}

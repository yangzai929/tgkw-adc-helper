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
 * 天眼查API工具类
 * 封装了调用天眼查开放平台接口的通用方法.
 */
class TianYanChaApi
{
    // 天眼查开放平台基础URL
    private $baseUrl = 'http://open.api.tianyancha.com/services/open';

    // API授权Token
    private $token;

    private $isMock = false;

    private $mockErrorData = '{
  "reason": "余额不足",
  "error_code": 300006
}';

    private $mockSuccessData = '{
  "result": {
    "historyNames": "贵州力源液压股份有限公司;",
    "cancelDate": null,
    "regStatus": "存续",
    "regCapital": "77800.32万人民币",
    "city": "毕节市",
    "staffNumRange": "5000-9999人",
    "bondNum": "600765",
    "historyNameList": [
      "贵州力源液压股份有限公司"
    ],
    "industry": "汽车制造业",
    "bondName": "中航重机",
    "revokeDate": null,
    "type": 1,
    "updateTimes": 1620622963000,
    "legalPersonName": "姬苏春",
    "revokeReason": "",
    "compForm": null,
    "regNumber": "520000000005018",
    "creditCode": "91520000214434146R",
    "property3": "AVIC Heavy Machinery Co.,Ltd.",
    "usedBondName": "力源液压->G力源->力源液压",
    "approvedTime": 1582646400000,
    "fromTime": 847900800000,
    "socialStaffNum": 9023,
    "actualCapitalCurrency": "人民币",
    "alias": "中航重机",
    "companyOrgType": "其他股份有限公司(上市)",
    "id": 11684584,
    "cancelReason": "",
    "orgNumber": "214434146",
    "toTime": null,
    "actualCapital": "77800.32万人民币",
    "estiblishTime": 847900800000,
    "regInstitute": "贵阳市市场监督管理局贵州双龙航空港经济区分局",
    "businessScope": "法律、法规、国务院决定规定禁止的不得经营；法律、法规、国务院决定规定应当许可（审批）的，经审批机关批准后凭许可（审批）文件经营;法律、法规、国务院决定规定无需许可（审批）的，市场主体自主选择经营。（股权投资及经营管理；军民共用液压件、液压系统、锻件、铸件、换热器、飞机及航空发动机附件，汽车零备件的研制、开发、制造、修理及销售；经营本企业自产机电产品、成套设备及相关技术的出口业务；经营本企业生产、科研所需的原辅材料、机械设备、仪器仪表、备品备件、零配件及技术的进口业务；开展本企业进料加工和“三来一补”业务。液压、锻件、铸件、换热器技术开发、转让和咨询服务；物流；机械冷热加工、修理修配服务。）",
    "taxNumber": "91520000214434146R",
    "regLocation": "贵州双龙航空港经济区机场路9号太升国际A栋3单元5层",
    "regCapitalCurrency": "人民币",
    "tags": "企业集团;存续;融资轮次;上市信息;项目品牌;投资机构;曾用名",
    "district": "威宁彝族回族苗族自治县",
    "economicFunctionZone1": null,
    "economicFunctionZone2": null,
    "districtCode": "520102",
    "bondType": "A股",
    "name": "中航重机股份有限公司",
    "percentileScore": 9696,
    "industryAll": {
            "category": "制造业",
            "categoryBig": "汽车制造业",
            "categoryMiddle": "改装汽车制造",
            "categorySmall": "",
            "categoryCodeFirst": "C",
            "categoryCodeSecond": "36",
            "categoryCodeThird": "363",
            "categoryCodeFourth": null
    },
    "isMicroEnt": 0,
    "base": "gz"
  },
  "reason": "ok",
  "error_code": 0
}';

    // Guzzle HTTP客户端
    private Client $client;

    /**
     * 构造函数，初始化token.
     * @param string $token 从天眼查数据中心获取的授权token
     * @throws InvalidArgumentException 如果token为空则抛出异常
     */
    public function __construct(string $token)
    {
        if (empty($token)) {
            throw new InvalidArgumentException('天眼查API Token不能为空');
        }
        $this->token = $token;
        $this->client = new Client([
            'timeout' => 30,
            'verify' => false, // 跳过SSL证书验证（生产环境建议开启）
        ]);
    }

    /**
     * 获取企业工商基本信息.
     * @param string $companyName 企业名称（需要准确）
     * @return array 解析后的企业信息数组
     * @throws Exception 调用失败时抛出异常
     */
    public function getCompanyBaseInfo(string $companyName): array
    {
        if ($this->isMock) {
            return json_decode($this->mockSuccessData, true);
        }
        // 参数校验
        if (empty($companyName)) {
            throw new InvalidArgumentException('企业名称不能为空');
        }

        // 构建请求URL
        $apiPath = '/ic/baseinfo/normal';
        $params = [
            'keyword' => $companyName,
        ];
        $url = $this->buildRequestUrl($apiPath, $params);

        // 发送请求并获取结果
        $response = $this->sendRequest($url);

        // 将JSON字符串转为数组并返回
        $result = json_decode($response, true);

        // 检查JSON解析是否成功
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
     * 发送HTTP请求
     * @param string $url 请求地址
     * @return string 响应内容
     * @throws Exception 请求失败时抛出异常
     */
    private function sendRequest(string $url): string
    {
        try {
            $response = $this->client->get($url, [
                'headers' => [
                    'Authorization' => $this->token,
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

//// ==================== 使用示例 ====================
//try {
//    // 初始化工具类（替换为你的真实token）
//    $tycApi = new TianYanChaApi('2ed59cfb-097b-4411-9e31-6d2b1fc3256c');
//
//    // 获取企业信息
//    $companyInfo = $tycApi->getCompanyBaseInfo('中航重机股份有限公司');
//
//    // 打印结果
//    echo "企业工商信息获取成功：\n";
//    print_r($companyInfo);
//    // {
//    //    "error_code": 300006,
//    //    "reason": "余额不足"
//    // }
//} catch (Exception $e) {
//    // 捕获并处理异常
//    echo '调用失败：' . $e->getMessage() . "\n";
//}

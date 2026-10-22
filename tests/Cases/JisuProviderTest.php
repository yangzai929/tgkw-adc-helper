<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace HyperfTest\Cases;

use TgkwAdc\Helper\CompanyVerify\Provider\JisuProvider;
use TgkwAdc\Utils\CompanyInfo\JisuApi;

/**
 * @internal
 * @coversNothing
 */
class JisuProviderTest extends AbstractTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! defined('BASE_PATH')) {
            define('BASE_PATH', sys_get_temp_dir());
        }
    }

    public function testDetailUnwrapsResultBasic(): void
    {
        $api = $this->createMock(JisuApi::class);
        $api->expects($this->once())
            ->method('query')
            ->with(['company' => '天宫通联（深圳）科技有限公司'])
            ->willReturn([
                'status' => 0,
                'msg' => 'ok',
                'result' => [
                    'basic' => [
                        'name' => '天宫通联（深圳）科技有限公司',
                        'orgno' => '354440644',
                        'regno' => '440307114474390',
                        'creditno' => '91440300354440644F',
                        'legalperson' => '陈成华',
                        'regcapital' => '500',
                        'regcapitalcn' => '500.000000万人民币',
                        'regcapitalcur' => '人民币',
                        'status' => '存续（在营、开业、在册）',
                        'type' => '有限责任公司（法人独资）',
                        'startdate' => '2015-11-27',
                        'enddate' => '5000-01-01',
                        'regaddress' => '深圳市龙岗区宝龙街道',
                        'regorgan' => '深圳市市场监督管理局',
                        'scope' => '一般经营项目',
                        'nicname' => '批发和零售业',
                        'insurenum' => '4',
                    ],
                ],
            ]);

        $provider = new JisuProvider($api);
        $company = $provider->detail('天宫通联（深圳）科技有限公司');

        self::assertNotNull($company);
        self::assertSame('天宫通联（深圳）科技有限公司', $company->name);
        self::assertSame('91440300354440644F', $company->creditCode);
        self::assertSame('陈成华', $company->legalPersonName);
        self::assertSame('500.000000万人民币', $company->regCapital);
        self::assertSame('jisu', $company->provider);
    }

    public function testSearchUnwrapsBasic(): void
    {
        $api = $this->createMock(JisuApi::class);
        $api->expects($this->once())
            ->method('search')
            ->with('天宫通联')
            ->willReturn([
                'status' => 0,
                'msg' => 'ok',
                'result' => [
                    'list' => [
                        ['basic' => ['name' => '天宫通联（深圳）科技有限公司', 'creditno' => '91440300354440644F']],
                    ],
                ],
            ]);

        $provider = new JisuProvider($api);
        $companies = $provider->search('天宫通联');

        self::assertCount(1, $companies);
        self::assertSame('天宫通联（深圳）科技有限公司', $companies[0]->name);
    }

    public function testDetailReturnsNullWhenUpstreamReportsNotFound(): void
    {
        $api = $this->createMock(JisuApi::class);
        $api->expects($this->once())
            ->method('query')
            ->with(['company' => '不存在的企业'])
            ->willReturn([
                'status' => 1,
                'msg' => '未查询到企业信息',
                'result' => null,
            ]);

        $provider = new JisuProvider($api);

        self::assertNull($provider->detail('不存在的企业'));
    }
}
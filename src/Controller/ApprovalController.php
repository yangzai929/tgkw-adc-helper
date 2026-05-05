<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Controller;

use Exception;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\PostMapping;
use TgkwAdc\Helper\AesHelper;
use TgkwAdc\Helper\ApiResponseHelper;
use TgkwAdc\Helper\Log\LogHelper;

#[Controller]
class ApprovalController extends AbstractController
{
    /*
     * 审批回调
     */
    #[PostMapping(path: '/approval/callback')]
    public function callback()
    {
        $dataEncrypt = $this->request->input('data'); // 加密的数据

        try {
            $data = AesHelper::decrypt($dataEncrypt);
            LogHelper::info('收到的审批回调数据：',[$data],filename: 'approvalCallback');
            $data = json_decode($data, true);
        } catch (Exception $e) {
            LogHelper::error('error', [$e->getMessage()]);
            return ApiResponseHelper::error('Error request，parameter[data] error：' . $e->getMessage());
        }

        $config = config('approval');
        if (! empty($config)) {
            return container()->get($config[$data['third_label']])->approveCallBack($data);
        }
        LogHelper::info('config没有配置审批回调文件');
        return ApiResponseHelper::success();
    }
}

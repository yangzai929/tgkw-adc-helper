<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\Constants\Code;

use TgkwAdc\Annotation\EnumCode;
use TgkwAdc\Annotation\EnumCodeInterface;
use TgkwAdc\Annotation\EnumCodePrefix;
use TgkwAdc\Trait\EnumCodeGet;

#[EnumCodePrefix(prefixCode: 100, info: '公共错误')]
enum CommonCode: int implements EnumCodeInterface
{
    use EnumCodeGet;

    #[EnumCode(msg: '参数错误，tenant_id不能为空', i18nMsg: ['en' => '参数错误，tenant_id不能为空', 'zh_hk' => '参数错误，tenant_id不能为空'])]
    case EMPTY_TENANT_ID = 999;

    #[EnumCode(msg: '请登录', i18nMsg: ['en' => 'Please log in', 'zh_hk' => '請登入'])]
    case NEED_LOGIN = 1;

    #[EnumCode(msg: '登录状态已过期，请重新登录！', i18nMsg: ['en' => 'Login status has expired, please log in again!', 'zh_hk' => '登入狀態已過期，請重新登入'])]
    case LOGIN_EXPIRED = 2;

    #[EnumCode(msg: '参数错误', i18nMsg: ['en' => 'Parameter error', 'zh_hk' => '參數錯誤'])]
    case PARAM_ERROR = 3;

    #[EnumCode(msg: '用户信息不存在,请重新注册登录！', i18nMsg: ['en' => 'User information does not exist, please register and log in again!', 'zh_hk' => '用戶信息不存在,請重新註冊登入！'])]
    case USER_NOT_EXITS = 4;

    #[EnumCode(msg: '您已被移出该机构!', i18nMsg: ['en' => 'You have been removed from the organization!', 'zh_hk' => '您已被移出该机构!'])]
    case USER_NOT_IN_ORG = 5;

    #[EnumCode(msg: '需要内网才能访问该接口（当前IP：{ip}）', i18nMsg: ['en' => 'Need intranet to access this interface (current IP: {ip})', 'zh_hk' => '需要內網才能訪問該接口（當前IP：{ip}）'])]
    case VISIT_NEED_INTRANET = 6;

    #[EnumCode(msg: '账号异常!未绑定角色身份', i18nMsg: ['en' => 'Account abnormal! Not bound to role identity', 'zh_hk' => '帳號異常!未綁定角色身份'])]
    case NOT_BIND_ROLE = 7;

    #[EnumCode(msg: '无权访问', i18nMsg: ['en' => 'No authority to access', 'zh_hk' => '無權訪問'])]
    case AUTH_ERROR = 8;

    #[EnumCode(msg: '无权访问（{action}）', i18nMsg: ['en' => 'No authority to access ({action})', 'zh_hk' => '無權訪問（{action}）'])]
    case AUTH_ERROR_ACTION = 9;

    #[EnumCode(msg: '保存失败，请重试', i18nMsg: ['en' => 'Failed to save, please try again', 'zh_hk' => '儲存失敗，請重試'])]
    case SAVE_FAILED = 10;

    #[EnumCode(msg: '保存成功', i18nMsg: ['en' => 'Saved successfully', 'zh_hk' => '儲存成功'])]
    case SAVE_SUCCESS = 11;

    #[EnumCode(msg: '操作成功', i18nMsg: ['en' => 'Operation successful', 'zh_hk' => '操作成功'])]
    case OPERATION_SUCCESS = 12;

    #[EnumCode(msg: '操作失败，请重试', i18nMsg: ['en' => 'Operation failed, please try again', 'zh_hk' => '操作失敗，請重試'])]
    case OPERATION_FAILED = 13;

    #[EnumCode(msg: '退出成功', i18nMsg: ['en' => 'Logout successful', 'zh_hk' => '退出成功，請重試'])]
    case LOGOUT_SUCCESS = 14;

    #[EnumCode(msg: '退出失败，请重试', i18nMsg: ['en' => 'Logout failed, please try again', 'zh_hk' => '退出失敗，請重試'])]
    case LOGOUT_FAILED = 15;

    #[EnumCode(msg: '账号异常，请重新登录', i18nMsg: ['en' => 'Account abnormal, please log in again', 'zh_hk' => '帳號異常，請重新登入'])]
    case ACCOUNT_ABNORMAL = 16;

    #[EnumCode(msg: '导入成功', i18nMsg: ['en' => 'Import successful', 'zh_hk' => '導入成功'])]
    case IMPORT_SUCCESS = 17;

    #[EnumCode(msg: '导入失败，请重试', i18nMsg: ['en' => 'Import failed, please try again', 'zh_hk' => '導入失敗，請重試'])]
    case IMPORT_FAILED = 18;

    #[EnumCode(msg: '导出成功', i18nMsg: ['en' => 'Export successful', 'zh_hk' => '導出成功'])]
    case EXPORT_SUCCESS = 19;

    #[EnumCode(msg: '导出失败，请重试', i18nMsg: ['en' => 'Export failed, please try again', 'zh_hk' => '導出失敗，請重試'])]
    case EXPORT_FAILED = 20;

    #[EnumCode(msg: '未查询到该条数据，请检查该数据是否存在', i18nMsg: ['en' => 'The data was not found. Please check whether the data exists.', 'zh_hk' => '未查詢到該條數據，請檢查該數據是否存在'])]
    case DATA_NOT_FOUND = 21;

    #[EnumCode(msg: '提交审批成功', i18nMsg: ['en' => 'Submit approval successful', 'zh_hk' => '提交審批成功'])]
    case APPROVAL_SUBMIT_SUCCESS = 22;

    #[EnumCode(msg: '提交审批失败，请重试', i18nMsg: ['en' => 'Submit approval failed, please try again', 'zh_hk' => '提交審批失敗，請重試'])]
    case APPROVAL_SUBMIT_FAILED = 23;

    #[EnumCode(msg: '提交成功', i18nMsg: ['en' => 'Submit successful', 'zh_hk' => '提交成功'])]
    case SUBMIT_SUCCESS = 24;

    #[EnumCode(msg: '提交失败，请重试', i18nMsg: ['en' => 'Submit failed, please try again', 'zh_hk' => '提交失敗，請重試'])]
    case SUBMIT_FAILED = 25;

    #[EnumCode(msg: '服务异常（{service_name}）[{error_msg}]', i18nMsg: ['en' => 'Service exception ({service_name}) [{error_msg}]', 'zh_hk' => '服務異常（{service_name}）[{error_msg}]'])]
    case SERVICE_EXCEPTION = 26;

    #[EnumCode(msg: '请上传文件', i18nMsg: ['en' => 'Please upload a file', 'zh_hk' => '請上傳文件'])]
    case UPLOAD_FILE_EMPTY = 27;

    #[EnumCode(msg: '文件格式（{file_ext}）不允许，只允许（{allow_ext}）', i18nMsg: ['en' => 'File format ({file_ext}) is not allowed, only allow ({allow_ext})', 'zh_hk' => '文件格式（{file_ext}）不允许，只允許（{allow_ext}）'])]
    case FILE_FORMAT_NOT_ALLOW = 28;

    #[EnumCode(msg: '绑定成功', i18nMsg: ['en' => 'Bind successful', 'zh_hk' => '綁定成功'])]
    case BIND_SUCCESS = 29;

    #[EnumCode(msg: '绑定失败，请重试', i18nMsg: ['en' => 'Binding failed, please try again', 'zh_hk' => '綁定失敗，請重試'])]
    case BIND_FAILED = 30;

    #[EnumCode(msg: '手机号格式不正确', i18nMsg: ['en' => 'Phone number format is incorrect', 'zh_hk' => '手機號格式不正確'])]
    case PHONE_FORMAT_ERROR = 31;

    #[EnumCode(msg: '请先删除子数据后，再删除此数据', i18nMsg: ['en' => 'Please delete the sub-data first, then delete this data', 'zh_hk' => '請先刪除子數據後，再刪除此數據'])]
    case HAS_SUB_DATA = 33;

    #[EnumCode(msg: '文件标识未传递，请重试', i18nMsg: ['en' => 'File identifier not passed, please try again', 'zh_hk' => '文件標識未傳遞，請重試'])]
    case IMPORT_FILE_ID_EMPTY = 34;

    #[EnumCode(msg: '文件已失效，请重新导入后下载', i18nMsg: ['en' => 'File has expired, please re-import and download', 'zh_hk' => '文件已失效，請重新導入後下載'])]
    case IMPORT_FILE_EXPIRED = 35;

    #[EnumCode(msg: '字段（{field}）不能为空', i18nMsg: ['en' => 'Field ({field}) cannot be empty', 'zh_hk' => '欄位（{field}）不能為空'])]
    case PARAMS_EMPTY_WITH_FIELD = 36;

    #[EnumCode(msg: '字段（{field}）错误', i18nMsg: ['en' => 'Field ({field}) error', 'zh_hk' => '欄位（{field}）錯誤'])]
    case PARAMS_WRONG_WITH_FIELD = 37;

    #[EnumCode(msg: '邮箱格式错误', i18nMsg: ['en' => 'Email format error', 'zh_hk' => '郵箱格式錯誤'])]
    case EMAIL_RULE_ERROR = 38;

    #[EnumCode(msg: '图片mime类型（{file_mime}）不允许，只允许（{allow_mime}）', i18nMsg: ['en' => 'Image mime type ({file_mime}) is not allowed, only allow ({allow_mime})', 'zh_hk' => '圖片mime類型（{file_mime}）不允许，只允許（{allow_mime}）'])]
    case FILE_MIME_NOT_ALLOW = 39;
}

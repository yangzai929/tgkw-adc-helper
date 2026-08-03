<?php

declare(strict_types=1);
/**
 * This file is part of tgkw-adc.
 *
 * @link     https://www.tgkw.com
 * @document https://hyperf.wiki
 */

namespace TgkwAdc\JsonRpc\Public;

/**
 * 公共基础能力 JSON-RPC 接口（由 adc-public PublicService 实现）.
 */
interface PublicServiceInterface
{
    /**
     * 标记单个文件是否已被业务占用.
     *
     * @param string $object_key 文件 object_key（存储对象键）
     * @param int $is_used 占用状态：1=已占用，0=未占用
     * @return bool
     */
    public function handleFileUsed(string $object_key, int $is_used);

    /**
     * 批量标记文件占用状态.
     *
     * @param array<int, string> $object_keys 文件 object_key 列表
     * @param int $is_used 占用状态：1=已占用，0=未占用
     * @return bool
     */
    public function handleFilesUsed(array $object_keys, int $is_used);

    /**
     * 查询单个文件信息.
     *
     * @param string $object_key 文件 object_key
     * @return mixed
     */
    public function getFileInfo(string $object_key);

    /**
     * 批量查询文件信息（按 object_key 索引）.
     *
     * @param array<int, string> $object_keys 文件 object_key 列表
     * @return mixed
     */
    public function getFilesInfo(array $object_keys);

    /**
     * 查询省市区列表.
     *
     * @param array $params 查询条件（如 parent_code、level 等）
     * @return mixed
     */
    public function getRegion(array $params);

    /**
     * 阿里云 OCR 识别（与 HTTP POST /v1/ocr 同能力）.
     *
     * 参数说明：
     * - recognize_type (必填) all_text|general_structure|idcard|passport|international_idcard|business_license|international_business_license
     * - image (必填) 公网 URL / Base64
     * - type / options / keys / country / llm_rec / output_figure / output_quality_info 见实现侧注释
     *
     * @param array $params 识别参数
     * @return array{result: array}
     */
    public function ocr(array $params);

    /**
     * 企业核验（与 HTTP POST /v1/company-verify 同能力）.
     *
     * 参数说明：
     * - company_name (必填) 企业名称
     * - credit_code (选填) 统一社会信用代码
     *
     * @param array $params 核验参数
     * @return array{verified: bool, driver: string, company: null|array}
     */
    public function companyVerify(array $params);
}

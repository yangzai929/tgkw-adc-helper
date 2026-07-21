# 阿里云 OCR 封装 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 在 `tgkw-adc/helper` 中落地静态 `OcrHelper`，封装阿里云 OCR 7 类识别能力。

**Architecture:** 对齐 `CaptchaVerifyService`：`systemConfig` 取 AK、统一图片入参解析、SDK 调用、`LogHelper` + `BusinessException`。

**Tech Stack:** PHP 8.1+ / Hyperf / `alibabacloud/ocr-api-20210707` 3.1.3

## Global Constraints

- 依赖版本固定：`alibabacloud/ocr-api-20210707` 3.1.3
- 风格对齐：`src/Helper/Captcha/CaptchaVerifyService.php`
- 配置键：`aliyun_ocr_access_key_id` / `aliyun_ocr_access_key_secret` / `aliyun_ocr_endpoint`
- 不新增 HTTP/RPC 接口

---

### Task 1: 错误码

**Files:**
- Modify: `src/Constants/Code/CommonCode.php`

- [x] 新增 `OCR_FAILED = 41`（中/英/繁）

### Task 2: OcrHelper

**Files:**
- Create: `src/Helper/Ocr/OcrHelper.php`

- [x] `createClient` / `getSystemConfig` / `resolveImage` / `applyImage`
- [x] 7 个 recognize 方法 + 统一 `invoke` 异常处理
- [x] 语法检查 `php -l`

### Task 3: 自检

- [x] 确认命名空间可自动加载（PSR-4 `TgkwAdc\` → `src/`）
- [x] 对照设计文档逐项核对 API

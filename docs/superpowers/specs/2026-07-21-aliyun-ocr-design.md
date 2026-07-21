# 阿里云 OCR 服务封装设计

日期：2026-07-21  
包：`tgkw-adc/helper`  
依赖：`alibabacloud/ocr-api-20210707` 3.1.3

## 目标

在公共基础包中封装阿里云 OCR 调用能力，供各 ADC 微服务直接使用，风格对齐现有 `CaptchaVerifyService`。

## 范围

### 本期包含

| 能力 | SDK 方法 |
|---|---|
| COCR 统一识别 | `RecognizeAllText` |
| 通用票证抽取 | `RecognizeGeneralStructure` |
| 身份证识别 | `RecognizeIdcard` |
| 国际护照识别 | `RecognizePassport` |
| 国际身份证识别 | `RecognizeInternationalIdcard` |
| 营业执照识别 | `RecognizeBusinessLicense` |
| 国际企业执照识别 | `RecognizeInternationalBusinessLicense` |

### 本期不包含

- HTTP Controller / JSON-RPC 接口（由业务服务自行封装）
- 识别结果落库、缓存
- Mock 模式

## 架构

```
业务 Service
    │
    ▼
TgkwAdc\Helper\Ocr\OcrHelper   （静态入口）
    │
    ├── createClient()           凭据 / Endpoint
    ├── resolveImage()           URL | 本地路径 | Base64/二进制 → url/body
    └── 各 recognize* 方法       调用 Ocrapi SDK
```

文件：`src/Helper/Ocr/OcrHelper.php`（唯一新增实现文件，另在 `CommonCode` 增加错误码）

## 配置

从 `cfg('systemConfig')` 读取（与 Captcha 一致）：

| 配置键 | 必填 | 说明 |
|---|---|---|
| `aliyun_ocr_access_key_id` | 是 | AccessKey ID |
| `aliyun_ocr_access_key_secret` | 是 | AccessKey Secret |
| `aliyun_ocr_endpoint` | 否 | 默认 `ocr-api.cn-hangzhou.aliyuncs.com` |

## 对外 API

统一图片入参 `$image` 支持：

1. 公网 URL（`http://` / `https://`）
2. 本地文件路径
3. 二进制内容或 Base64（可带 `data:image/...;base64,` 前缀）

| 方法 | 说明 | 额外参数 |
|---|---|---|
| `recognizeAllText($image, string $type = 'Advanced', array $options = [])` | COCR 统一识别 | `$type`；`$options` 透传可选 SDK 配置 |
| `recognizeGeneralStructure($image, array $keys = [])` | 通用票证抽取 | `$keys` 字段名列表 |
| `recognizeIdcard($image, array $options = [])` | 身份证 | 可选输出控制 |
| `recognizePassport($image)` | 国际护照 | — |
| `recognizeInternationalIdcard($image, string $country)` | 国际身份证 | `$country` 国家码 |
| `recognizeBusinessLicense($image)` | 营业执照 | — |
| `recognizeInternationalBusinessLicense($image, string $country)` | 国际企业执照 | `$country` 国家码 |

### 返回值

成功：`array`，即 SDK `response->body->toMap()`，保留阿里云原始结构。

失败：抛 `BusinessException`，并用 `LogHelper` 记录（通道名 `aliyun_ocr`）。

## 错误处理

| 场景 | 错误码 |
|---|---|
| 缺配置 / 图片无效 / 参数非法 | `CommonCode::PARAM_ERROR` |
| SDK / 网络调用失败 | `CommonCode::OCR_FAILED`（新增，值为 `41`） |

## 调用示例

```php
use TgkwAdc\Helper\Ocr\OcrHelper;

$result = OcrHelper::recognizeAllText('https://example.com/a.jpg');
$result = OcrHelper::recognizeGeneralStructure('/tmp/ticket.jpg', ['发票号码', '金额']);
$result = OcrHelper::recognizeIdcard($binaryOrBase64);
$result = OcrHelper::recognizePassport($url);
$result = OcrHelper::recognizeInternationalIdcard($url, 'USA');
$result = OcrHelper::recognizeBusinessLicense($url);
$result = OcrHelper::recognizeInternationalBusinessLicense($url, 'USA');
```

## 实现约束

- 遵循 [ADC 后端开发规范](https://docs-adc.tgkw.work/ADC-项目后端开发规范.html)
- 凭据初始化方式对齐 `Helper/Captcha/CaptchaVerifyService`
- 使用 `declare(strict_types=1)` 与现有命名空间/注释风格
- 不在 helper 包内暴露 HTTP 路由

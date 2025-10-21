# 天宫开物 ADC Helper

基于 Hyperf 框架的 PHP 扩展包，提供基础工具类、助手函数、全局中间件、基础验证Request类、基础资源Resource类等。

## 环境要求

- PHP >= 8.1
- Hyperf >= 3.1

## 安装

```bash
composer require tgkw-adc/helper
```

## 快速开始

### 1. 助手函数

```php
use function TgkwAdc\cfg;
use function TgkwAdc\redis;
use function TgkwAdc\toRmb;
use function TgkwAdc\math_add;

// 获取配置
$config = cfg('app.name', 'default');

// 获取 Redis 实例
$redis = redis('default');

// 数字转中文大写金额
echo toRmb(123.45); // 壹佰贰拾叁元肆角伍分

// 精确数学运算
echo math_add('0.1', '0.2'); // 0.30
```

### 2. API 响应

```php
use TgkwAdc\Helper\ApiResponseHelper;

// 成功响应
return ApiResponseHelper::success($data, '操作成功');

// 错误响应
return ApiResponseHelper::error('参数错误', $errors, null, 400);
```

### 3. JWT 认证

```php
use TgkwAdc\Helper\JwtHelper;

// 初始化
JwtHelper::init();

// 生成令牌
$token = JwtHelper::createToken(['user_id' => 123], 3600);

// 解析令牌
$payload = JwtHelper::parseToken($token);

// 从请求获取载荷
$payload = JwtHelper::getPayloadFromRequest($request);
```

### 4. 日志记录

```php
use TgkwAdc\Helper\Log\LogHelper;

// 基本日志
LogHelper::info('用户登录', ['user_id' => 123]);
LogHelper::error('操作失败', ['error' => '数据库连接超时']);

// 业务日志
LogHelper::business('订单创建', ['order_id' => 456], 'business', 'order-create');

// 异常日志
LogHelper::exception($exception, '处理异常', ['user_id' => 123]);
```

### 5. 枚举注解

```php
use TgkwAdc\Annotation\EnumCode;
use TgkwAdc\Annotation\EnumCodePrefix;
use TgkwAdc\Trait\EnumCodeGet;

#[EnumCodePrefix(prefixCode: 1000)]
enum UserStatus: int
{
    use EnumCodeGet;

    #[EnumCode('正常', ['en' => 'Normal', 'zh_hk' => '正常'])]
    case NORMAL = 1;

    #[EnumCode('禁用', ['en' => 'Disabled', 'zh_hk' => '禁用'])]
    case DISABLED = 2;
}

// 使用
$status = UserStatus::NORMAL;
echo $status->getMsg(); // 正常
echo $status->getCode(); // 100001
echo $status->getI18nMsg('en'); // Normal
```

## 详细功能

### 助手函数

#### 配置和缓存
- `cfg($key, $default)` - 获取配置值
- `redis($pool)` - 获取 Redis 实例

#### 字符串处理
- `mb_trim($str, $char, $encoding)` - 多字节字符串修剪
- `mb_ltrim($str, $char, $encoding)` - 多字节左修剪
- `mb_rtrim($str, $trim, $encoding)` - 多字节右修剪
- `safeGetValue($string)` - SQL 安全处理

#### 数字处理
- `toRmb($num)` - 数字转中文大写金额
- `numToCn($num)` - 阿拉伯数字转中文数字
- `priceFormat($price, $format)` - 价格格式化
- `math_add($a, $b, $scale)` - 精确加法
- `math_sub($a, $b, $scale)` - 精确减法
- `math_mul($a, $b, $scale)` - 精确乘法
- `math_div($a, $b, $scale)` - 精确除法
- `math_mod($a, $b)` - 精确求余
- `math_comp($a, $b, $scale)` - 数值比较

#### 数组处理
- `object_array($array)` - 对象转数组
- `second_array_unique_bykey($arr, $key)` - 二维数组去重
- `percentArray($array)` - 数组百分比转换

#### 日期时间
- `getDateYMD($startDate, $endDate, $type)` - 获取日期范围
- `getMonthsCovered($startDate, $endDate)` - 计算跨越月数
- `getDiffDay($startDate, $endDate)` - 计算相差天数
- `getDiffYear($startDate, $endDate)` - 计算相差年数
- `delHis($date)` - 去除时分秒
- `isDateValid($date, $formats)` - 校验日期格式
- `isDateTime($dateTime)` - 校验日期时间

#### 工具函数
- `findNum($string)` - 提取字符串中的数字
- `handelUrlAliasParam($alias, $urlPatch)` - 处理 URL 参数
- `getRate($num)` - 比例转百分比
- `isJson($content)` - 检查 JSON 格式
- `getCollectionRate($val1, $val2)` - 计算收缴率
- `createNonceStr($length)` - 生成随机字符串

### 中间件

#### 语言检测中间件
```php
use TgkwAdc\Middleware\LocaleMiddleware;

// 在 config/autoload/middlewares.php 中注册
return [
    'http' => [
        LocaleMiddleware::class,
    ],
];
```

支持的语言检测方式：
- URL 参数 `?lang=zh`
- 请求体参数 `{"lang": "zh"}`
- Accept-Language 头
- X-Language 头
- Cookie 中的 locale

#### 请求追踪中间件
```php
use TgkwAdc\Middleware\TraceIdMiddleware;

// 自动为每个请求生成唯一追踪ID
```

### 资源类

#### 基础资源类
```php
use TgkwAdc\Resource\BaseResource;

class UserResource extends BaseResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->formatDate($this->created_at),
        ];
    }
}

// 使用
return new UserResource($user)
    ->withMeta(['total' => 100])
    ->withStats(['views' => 50]);
```

### 日志系统

#### 配置日志
复制配置文件：
```bash
cp vendor/tgkw-adc/helper/src/Helper/Log/logger.example.php config/autoload/logger.php
```

#### 日志通道
- `default` - 默认日志
- `business` - 业务日志
- `access` - 访问日志
- `system` - 系统日志
- `exception` - 异常日志

#### 自定义日志文件名
```php
// 按模块分类
LogHelper::info('订单创建', ['order_id' => 456], 'business', 'order-create');

// 按日期分类
$date = date('Y-m-d');
LogHelper::info('每日统计', ['count' => 100], 'statistics', 'default', "daily-{$date}");

// 按用户分类
LogHelper::info('用户操作', ['action' => 'login'], 'user', 'default', "user-{$userId}");
```

### 国际化支持

#### 语言常量
```php
use TgkwAdc\Constants\LocaleConstants;

// 支持的语言
$locales = LocaleConstants::getSupportedLocaleCodes();
// ['zh' => 'zh_CN', 'en' => 'en_US', 'zh_hk' => 'zh_HK', 'zh_tw' => 'zh_TW']

// 默认语言
$default = LocaleConstants::getDefaultLocale(); // zh_CN
```

#### 国际化助手
```php
use TgkwAdc\Helper\Intl\I18nHelper;

// 获取当前语言
$locale = I18nHelper::getNowLang();

// 获取翻译文本
$text = I18nHelper::getText('welcome.message', ['name' => 'John']);
```

## 配置说明

### JWT 配置
```php
// config/autoload/jwt.php
return [
    'secret' => env('JWT_SECRET', 'your-secret-key'),
    'alg' => env('JWT_ALG', 'HS256'),
];
```

### 日志配置
```php
// config/autoload/logger.php
return [
    'default' => [
        'handler' => [
            'class' => Monolog\Handler\RotatingFileHandler::class,
            'constructor' => [
                'filename' => BASE_PATH . '/runtime/logs/app.log',
                'level' => Monolog\Level::Info,
                'maxFiles' => 30,
            ],
        ],
        'formatter' => [
            'class' => TgkwAdc\Helper\Log\CustomJsonFormatter::class,
        ],
        'processors' => [
            TgkwAdc\Helper\Log\AppendRequestIdProcessor::class,
        ],
    ],
];
```

## 开发工具

### 代码格式化
```bash
vendor/bin/php-cs-fixer fix src
```

### 静态分析
```bash
vendor/bin/phpstan analyse --memory-limit 1024M -l 0 ./src
```


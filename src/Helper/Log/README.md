# 日志助手类使用说明

## 概述

`LogHelper` 是基于 Hyperf 框架的日志组件封装的助手类，提供了简单易用的日志记录功能。

## 基本使用

### 1. 基本日志记录

```php
use TgkwAdc\Helper\Log\LogHelper;

// 记录不同级别的日志
LogHelper::debug('调试信息', ['user_id' => 123]);
LogHelper::info('用户登录成功', ['user_id' => 123, 'ip' => '192.168.1.1']);
LogHelper::warning('密码错误次数过多', ['user_id' => 123]);
LogHelper::error('数据库连接失败', ['error' => 'Connection timeout']);
LogHelper::critical('系统严重错误', ['error' => 'Memory limit exceeded']);
```

### 2. 自定义日志文件名

```php
// 使用自定义文件名记录日志
LogHelper::info('业务日志', ['order_id' => 456], 'business', 'default', 'order-business');
LogHelper::error('支付错误', ['payment_id' => 789], 'payment', 'default', 'payment-error');
LogHelper::debug('调试信息', ['user_id' => 123], 'user', 'default', 'user-debug');

// 业务日志使用自定义文件名
LogHelper::business('订单创建', ['order_id' => 456], 'business', 'order-create');
LogHelper::access('API访问', ['path' => '/api/users'], 'access', 'api-access');
LogHelper::system('系统状态', ['memory' => '80%'], 'system', 'system-monitor');
LogHelper::exception($exception, '处理异常', ['user_id' => 123], 'exception', 'user-exception');
```

### 3. 指定日志通道

```php
// 使用不同的日志通道
LogHelper::info('业务日志', ['order_id' => 456], 'business');
LogHelper::info('访问日志', ['path' => '/api/users', 'method' => 'GET'], 'access');
LogHelper::info('系统日志', ['action' => 'cache_clear'], 'system');
```

### 4. 异常日志记录

```php
try {
    // 业务逻辑
    throw new \Exception('业务异常');
} catch (\Throwable $e) {
    LogHelper::exception($e, '处理用户订单时发生异常', [
        'user_id' => 123,
        'order_id' => 456
    ]);
}
```

### 5. 获取日志记录器实例

```php
// 获取默认日志记录器
$logger = LogHelper::get();

// 获取指定通道的日志记录器
$businessLogger = LogHelper::get('business');
$accessLogger = LogHelper::get('access', 'access');

// 获取动态日志记录器（支持自定义文件名）
$dynamicLogger = LogHelper::getDynamic('business', 'order-business', 'info');
```

## 配置说明

### 1. 复制配置文件

将 `logger.example.php` 复制到项目的 `config/autoload/` 目录下，并重命名为 `logger.php`：

```bash
cp vendor/tgkw-adc/helper/src/Helper/Log/logger.example.php config/autoload/logger.php
```

### 2. 配置说明

- `default`: 默认日志配置，包含多个处理器
- `single`: 单文件日志处理器（支持日志轮转）
- `daily`: 按日期轮转的日志处理器
- `business`: 业务日志处理器（支持日志轮转）
- `access`: 访问日志处理器（支持日志轮转）
- `system`: 系统日志处理器（支持日志轮转）
- `exception`: 异常日志处理器（支持日志轮转）
- `api`: API模块日志处理器（支持日志轮转）
- `payment`: 支付模块日志处理器（支持日志轮转）

### 3. 日志轮转功能

所有日志配置都支持自动轮转，通过 `RotatingFileHandler` 实现：

- **自动按日期轮转**：每天自动创建新的日志文件
- **自动清理旧日志**：通过 `maxFiles` 参数控制保留天数
- **文件命名规则**：`原文件名-YYYY-MM-DD.log`

#### 轮转配置示例

```php
'handler' => [
    'class' => Handler\RotatingFileHandler::class,
    'constructor' => [
        'filename' => BASE_PATH . '/runtime/logs/app.log',
        'level' => Level::Info,
        'maxFiles' => 30, // 保留30天的日志
    ],
],
```

#### 不同模块的保留策略

- **一般日志**：保留30天
- **支付日志**：保留90天（更重要的业务数据）
- **调试日志**：保留30天

### 4. 自定义日志文件名

#### 方式1: 使用环境变量

```php
// 在配置文件中使用环境变量
$appEnv = env('APP_ENV', 'dev');
$appName = env('APP_NAME', 'hyperf');

'stream' => BASE_PATH . "/runtime/logs/{$appName}-{$appEnv}.log",
```

#### 方式2: 直接指定文件名

```php
'stream' => BASE_PATH . '/runtime/logs/my-app.log',
```

#### 方式3: 使用日期作为文件名

```php
'stream' => BASE_PATH . '/runtime/logs/app-' . date('Y-m-d') . '.log',
```

#### 方式4: 按模块分类

```php
// API模块日志
'stream' => BASE_PATH . '/runtime/logs/api-access.log',

// 支付模块日志
'filename' => BASE_PATH . '/runtime/logs/payment-business.log',
```

#### 方式5: 按功能分类

```php
// 订单相关日志
'stream' => BASE_PATH . '/runtime/logs/order-business.log',

// 用户相关日志
'stream' => BASE_PATH . '/runtime/logs/user-operation.log',
```

#### 方式6: 按服务分类

```php
// 微服务A的日志
'stream' => BASE_PATH . '/runtime/logs/service-a.log',

// 微服务B的日志
'stream' => BASE_PATH . '/runtime/logs/service-b.log',
```

### 3. 请求ID处理器

配置中包含了 `AppendRequestIdProcessor` 处理器，会自动为每条日志添加：
- `request_id`: 请求唯一标识
- `coroutine_id`: 协程ID

## 日志级别

支持的日志级别（按严重程度排序）：

1. `debug`: 调试信息
2. `info`: 一般信息
3. `notice`: 通知信息
4. `warning`: 警告信息
5. `error`: 错误信息
6. `critical`: 严重错误
7. `alert`: 警报
8. `emergency`: 紧急情况

## 注意事项

1. 不要在日志通道名称中使用请求相关的标识符（如 request_id），这会导致内存泄漏
2. 建议为不同类型的日志使用不同的通道名称
3. 异常日志会自动记录异常的详细信息，包括堆栈跟踪
4. 所有日志都会自动添加请求ID和协程ID（如果配置了处理器）

## 示例场景

### 用户操作日志

```php
// 用户登录
LogHelper::info('用户登录', [
    'user_id' => $userId,
    'ip' => $request->getServerParams()['remote_addr'],
    'user_agent' => $request->getHeaderLine('user-agent')
], 'business');

// 用户操作
LogHelper::info('用户操作', [
    'user_id' => $userId,
    'action' => 'create_order',
    'order_id' => $orderId
], 'business');
```

### API访问日志

```php
// 记录API访问
LogHelper::info('API访问', [
    'method' => $request->getMethod(),
    'path' => $request->getUri()->getPath(),
    'ip' => $request->getServerParams()['remote_addr'],
    'response_time' => $responseTime
], 'access');
```

### 系统监控日志

```php
// 系统状态
LogHelper::info('系统状态检查', [
    'memory_usage' => memory_get_usage(true),
    'cpu_usage' => sys_getloadavg()[0],
    'disk_usage' => disk_free_space('/')
], 'system');
```

## 自定义日志文件名使用示例

### 1. 通过参数自定义文件名

```php
// 基本用法：在最后一个参数指定文件名
LogHelper::info('用户登录', ['user_id' => 123], 'user', 'default', 'user-login');
// 生成文件：runtime/logs/user-login.log

// 业务日志自定义文件名
LogHelper::business('订单创建', ['order_id' => 456], 'business', 'order-create');
// 生成文件：runtime/logs/order-create.log

// 按日期分类的日志
$date = date('Y-m-d');
LogHelper::info('每日统计', ['count' => 100], 'statistics', 'default', "daily-stats-{$date}");
// 生成文件：runtime/logs/daily-stats-2024-01-15.log

// 按用户ID分类的日志
$userId = 12345;
LogHelper::info('用户操作', ['action' => 'profile_update'], 'user', 'default', "user-{$userId}");
// 生成文件：runtime/logs/user-12345.log

// 按模块分类的日志
LogHelper::error('支付失败', ['payment_id' => 789], 'payment', 'default', 'payment-error');
// 生成文件：runtime/logs/payment-error.log
```

### 2. 动态文件名示例

```php
// 按小时分类
$hour = date('H');
LogHelper::info('API访问', ['endpoint' => '/api/users'], 'api', 'default', "api-access-{$hour}");

// 按请求ID分类
$requestId = uniqid();
LogHelper::info('请求开始', ['path' => '/api/orders'], 'request', 'default', "request-{$requestId}");

// 按业务类型分类
$businessType = 'order';
LogHelper::business('业务操作', ['action' => 'create'], 'business', "{$businessType}-business");
```

### 3. 配置文件中的环境变量方式

在 `.env` 文件中设置：

```env
APP_NAME=my-project
APP_ENV=production
```

配置文件会自动生成：
- `my-project-production.log`
- `my-project-debug-production.log`
- `my-project-business-production.log`

### 4. 日志文件命名最佳实践

```php
// 推荐的日志文件命名规范
// 格式: {模块}-{功能}-{日期/ID}.log

// 按模块分类
LogHelper::info('API访问', ['endpoint' => '/api/users'], 'api', 'default', 'api-access');
LogHelper::error('支付错误', ['payment_id' => 789], 'payment', 'default', 'payment-error');

// 按功能分类
LogHelper::business('订单处理', ['order_id' => 456], 'business', 'order-process');
LogHelper::system('缓存清理', ['cache_type' => 'redis'], 'system', 'cache-cleanup');

// 按时间分类
$date = date('Y-m-d');
LogHelper::info('每日统计', ['count' => 100], 'statistics', 'default', "daily-{$date}");

// 按用户分类
$userId = 12345;
LogHelper::info('用户操作', ['action' => 'login'], 'user', 'default', "user-{$userId}");
```

# HTTP Access Log Middleware Refactor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 将旧 `OperationLogMiddleware` 重构为不影响业务响应、默认不采集请求/响应正文、覆盖成功与失败请求、带 Trace 和耗时的轻量 HTTP Access Log，并让 `adc-log` 兼容新旧消息且不再静默丢弃瞬时失败。

**Architecture:** 在 `tgkw-adc/helper` 新增 `HttpAccessLogMiddleware`，保留 `OperationLogMiddleware` 作为兼容别名。中间件只发送结构化元数据，发布失败被隔离；`adc-log` 消费者兼容新旧 Payload，先做本地过滤和字段规范化，再幂等落库，瞬时失败返回 `REQUEUE`。

**Tech Stack:** PHP 8.1+、Hyperf 3.1、PSR-15、RabbitMQ、PHPUnit 10、MySQL migrations。

---

## 文件结构

### `tgkw-adc/helper`

- Create: `src/Middleware/HttpAccessLogMiddleware.php` — 新的轻量访问日志中间件。
- Modify: `src/Middleware/OperationLogMiddleware.php` — 保留旧类名并继承新中间件。
- Modify: `src/Amqp/Producer/OperationLogProducer.php` — 收紧 Payload 类型。
- Create: `tests/Cases/HttpAccessLogMiddlewareTest.php` — 中间件行为测试。
- Modify: `README.md` — 说明访问日志定位、配置和升级方式。

### `adc-log`

- Create: `test/bootstrap.php` — 补齐 PHPUnit 启动文件。
- Create: `test/Cases/Amqp/OperationLogConsumerTest.php` — 新旧消息兼容、过滤、重试和幂等测试。
- Modify: `app/Amqp/Consumer/OperationLogConsumer.php` — 重构消费行为。
- Modify: `app/Model/OperationLog.php` — 增加新字段类型和可写字段。
- Create: `migrations/2026_08_22_000001_extend_operation_logs_for_access_log.php` — 增加事件、Trace、请求、耗时和响应大小字段及索引。

---

### Task 1: Helper 中间件行为测试

**Files:**
- Create: `tests/Cases/HttpAccessLogMiddlewareTest.php`

- [ ] **Step 1: 编写成功请求测试**

验证消息包含 `event_id`、请求发生时间、服务、租户、操作者、method、router、状态码、耗时、Trace、Request ID、User-Agent 和响应大小；同时验证默认 `request_data`、`response_data` 为空且响应 Body 未被读取。

- [ ] **Step 2: 运行测试并确认 RED**

Run:

```bash
vendor/bin/phpunit -c phpunit.xml tests/Cases/HttpAccessLogMiddlewareTest.php
```

Expected: FAIL，因为 `HttpAccessLogMiddleware` 尚不存在。

- [ ] **Step 3: 编写异常请求测试**

验证下游抛出异常时仍尝试发送状态为 500 的访问日志，并原样重新抛出业务异常。

- [ ] **Step 4: 编写 MQ 发布失败隔离测试**

验证 Producer 抛出异常时，成功 Response 仍原样返回，异常 Response 仍保留原业务异常。

- [ ] **Step 5: 编写匿名请求与系统管理员上下文测试**

验证没有登录上下文时仍记录匿名访问；System Token 使用 `SYS_ADMIN_CONTEXT`，Org Token 使用 `ORG_USER_CONTEXT`。

### Task 2: 实现 Helper 中间件

**Files:**
- Create: `src/Middleware/HttpAccessLogMiddleware.php`
- Modify: `src/Middleware/OperationLogMiddleware.php`
- Modify: `src/Amqp/Producer/OperationLogProducer.php`

- [ ] **Step 1: 实现最小 `HttpAccessLogMiddleware`**

实现 `try/catch/finally` 生命周期；不读取 Response Stream 内容；通过 `getSize()` 或 `Content-Length` 获取响应大小；在 `finally` 中发布结构化元数据。

- [ ] **Step 2: 实现上下文提取**

支持 Org、System、Base User 和 Anonymous Actor；生成 UUIDv7 `event_id`；写入 `occurred_at`、`trace_id`、`request_id`、`duration_ms` 和 `user_agent`。

- [ ] **Step 3: 实现生产端过滤与配置默认值**

读取 `access_log.enabled`、`access_log.exclude_routes`，默认过滤健康检查、favicon 和 `POST */query`；默认不采集请求和响应正文。

- [ ] **Step 4: 隔离日志发布故障**

捕获所有 `Throwable`，只记录最小错误上下文，不输出请求 Payload，不允许日志异常改变业务返回值。

- [ ] **Step 5: 保留旧类名兼容**

将 `OperationLogMiddleware` 改为继承 `HttpAccessLogMiddleware` 的兼容类，现有微服务配置无需立即修改。

- [ ] **Step 6: 运行 Helper 测试并确认 GREEN**

Run:

```bash
composer test
```

Expected: 全部通过。

### Task 3: Helper 文档和质量检查

**Files:**
- Modify: `README.md`
- Create: `docs/superpowers/plans/2026-08-22-operation-log-middleware-refactor.md`

- [ ] **Step 1: 更新 README**

明确访问日志不是业务审计；列出默认字段、默认不记录正文、配置覆盖和 `OperationLogMiddleware` 兼容策略。

- [ ] **Step 2: 运行代码风格检查**

Run:

```bash
composer cs-check
```

Expected: 无格式差异。

- [ ] **Step 3: 运行静态分析**

Run:

```bash
composer analyse
```

Expected: 退出码 0。

- [ ] **Step 4: 提交 Helper 变更**

```bash
git add src tests README.md docs/superpowers/plans/2026-08-22-operation-log-middleware-refactor.md
git commit -m "refactor(logging): make operation middleware safe and lightweight"
```

### Task 4: adc-log 消费者测试基础设施

**Files:**
- Create: `test/bootstrap.php`
- Create: `test/Cases/Amqp/OperationLogConsumerTest.php`

- [ ] **Step 1: 添加最小测试 Bootstrap**

加载 Composer Autoload、定义 `BASE_PATH`，不启动完整 HTTP 服务或真实数据库。

- [ ] **Step 2: 编写新 Payload 映射测试**

验证新字段映射到数据库结构，`occurred_at` 用作 `create_time`，不使用消费时间替代事件时间。

- [ ] **Step 3: 编写旧 Payload 兼容测试**

验证旧 `user_data`、`time`、`request_data` 和 `response_data` 消息仍可消费。

- [ ] **Step 4: 编写过滤、IP 增强降级和错误语义测试**

验证过滤消息直接 ACK；IP 查询失败仍保存基础日志；持久化异常返回 `REQUEUE`；非法 Payload 返回 `DROP`。

- [ ] **Step 5: 运行测试并确认 RED**

Run:

```bash
vendor/bin/co-phpunit --prepend test/bootstrap.php test/Cases/Amqp/OperationLogConsumerTest.php --colors=always
```

Expected: FAIL，因为消费者仍直接 ACK 异常且不支持新字段。

### Task 5: 实现 adc-log 消费兼容层

**Files:**
- Modify: `app/Amqp/Consumer/OperationLogConsumer.php`
- Modify: `app/Model/OperationLog.php`
- Create: `migrations/2026_08_22_000001_extend_operation_logs_for_access_log.php`

- [ ] **Step 1: 重构 Payload 校验和映射**

支持新旧消息，限制字符串长度，安全 JSON 编码，不打印完整请求或响应数据。

- [ ] **Step 2: 将 IP 增强改成非关键步骤**

IP 查询失败时 `ip_location` 留空并继续落库。

- [ ] **Step 3: 实现幂等写入**

新消息使用唯一 `event_id` 和 `insertOrIgnore`；旧消息 `event_id` 为 `null`，保持兼容。

- [ ] **Step 4: 修正 ACK 语义**

非法消息返回 `DROP`；过滤消息和成功/重复消息返回 `ACK`；持久化或基础设施异常返回 `REQUEUE`。

- [ ] **Step 5: 增加数据库字段**

新增 `event_id`、`request_id`、`user_agent`、`duration_ms`、`response_size`，为 `event_id`、`trace_id`、`request_id` 和常用查询组合建立索引。

- [ ] **Step 6: 运行消费者测试并确认 GREEN**

Run:

```bash
vendor/bin/co-phpunit --prepend test/bootstrap.php test/Cases/Amqp/OperationLogConsumerTest.php --colors=always
```

Expected: 全部通过。

### Task 6: adc-log 全量验证与提交

**Files:**
- All modified `adc-log` files

- [ ] **Step 1: 运行全量测试**

```bash
composer test
```

Expected: 全部通过。

- [ ] **Step 2: 运行代码风格检查**

```bash
composer cs-check
```

Expected: 无格式差异。

- [ ] **Step 3: 运行静态分析**

```bash
composer analyse
```

Expected: 退出码 0。

- [ ] **Step 4: 检查迁移可逆性**

确认 `down()` 只删除本次新增字段和索引，不误删现有 `trace_id` 或其他列。

- [ ] **Step 5: 提交 adc-log 变更**

```bash
git add app migrations test
git commit -m "refactor(logging): consume lightweight access logs safely"
```

### Task 7: 跨仓库契约验证

- [ ] **Step 1: 对比 Helper Payload 与 Consumer 字段**

确认所有新字段名称、类型和默认值完全一致。

- [ ] **Step 2: 运行两个仓库的测试、风格和静态分析**

Helper:

```bash
composer test
composer cs-check
composer analyse
```

adc-log:

```bash
composer test
composer cs-check
composer analyse
```

Expected: 所有命令退出码为 0。

- [ ] **Step 3: 检查 Git Diff 和工作区状态**

```bash
git diff --check
git status --short
```

Expected: 仅包含计划内文件，且提交后工作区干净。

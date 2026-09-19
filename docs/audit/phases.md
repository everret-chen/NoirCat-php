# 开发记录

## Phase 0：环境与地基

### 环境
- [x] 创建 GitHub 仓库（`origin` = everret-chen/NoirCat-php）
- [x] 初始化 Laravel 12 骨架（PHP 8.3.33 / SQLite / `database` 队列与缓存驱动）
- [x] 基础配置（`APP_NAME=NoirCat`、时区 `Asia/Shanghai`、`zh_CN` 默认 + `en` 兜底）
- [x] 静态分析工具链（larastan + PHPStan level 6）
- [ ] 接入 MySQL 8（Phase 3 之前完成，届时只改 `.env`）

### 地基
- [x] i18n：`lang/{zh_CN,en}`（common / api / auth / passwords / pagination / validation）+ `SetLocale` 中间件（`?lang=` → `X-Locale` → `Accept-Language`）
- [x] 统一响应：`App\Http\Responses\ApiResponse`（`{code, message, data}`，分页信息放 `meta`）
- [x] 错误码：`App\Enums\ErrorCode`（1xxx 认证 / 2xxx 权限 / 3xxx 校验与资源 / 4xxx 业务规则 / 5xxx 系统）
- [x] 异常处理：`App\Exceptions\ApiExceptionRenderer`（API 请求走信封、Web 请求保留 Blade 错误页；生产环境不泄露异常细节）
- [x] 审计日志：`audit_logs` 迁移 + `App\Models\AuditLog` + `App\Services\AuditLogService`（敏感字段递归脱敏）
- [x] 限流矩阵：`config/noircat.php` + `AppServiceProvider` 注册 6 个命名限流器（api / login / register / posts / uploads / search）
- [x] API 骨架：`routes/api.php` + `GET /api/health`
- [x] 测试：22 个用例 / 79 断言全部通过（响应信封、本地化、异常映射、限流、审计脱敏）
- [x] PHPStan level 6：0 错误

## Phase 1：用户认证（未开始）

- [ ] 安装 Laravel Sanctum 与 spatie/laravel-permission
- [ ] 注册 / 登录 / 登出 / me / 资料 / 头像上传
- [ ] 认证事件写入 `audit_logs`
- [ ] 产出 `docs/audit/auth-audit.md`

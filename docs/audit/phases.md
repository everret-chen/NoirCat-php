# 开发记录

## Phase 0：环境与地基

### 环境
- [x] 创建 GitHub 仓库（`origin` = everret-chen/NoirCat-php）
- [x] 初始化 Laravel 12 骨架（PHP 8.3.33 / SQLite / `database` 队列与缓存驱动）
- [x] 基础配置（`APP_NAME=NoirCat`、时区 `Asia/Shanghai`、`zh_CN` 默认 + `en` 兜底）
- [x] 静态分析工具链（larastan + PHPStan level 6）
- [x] 仓库治理（CI、SECURITY.md、CONTRIBUTING.md、PR/Issue 模板、Dependabot）
- [ ] 接入 MySQL 8（Phase 3 之前完成，届时只改 `.env`）

### 地基
- [x] i18n：`lang/{zh_CN,en}` + `SetLocale` 中间件（`?lang=` → `X-Locale` → `Accept-Language`）
- [x] 统一响应：`App\Http\Responses\ApiResponse`（`{code, message, data}`，分页信息放 `meta`）
- [x] 错误码：`App\Enums\ErrorCode`（1xxx 认证 / 2xxx 权限 / 3xxx 校验与资源 / 4xxx 业务 / 5xxx 系统）
- [x] 异常处理：`App\Exceptions\ApiExceptionRenderer`（API 走信封、Web 走 Blade 错误页；生产不泄露细节）
- [x] 审计日志：`audit_logs` + `AuditLog` + `AuditLogService`（敏感字段递归脱敏）
- [x] 限流矩阵：`config/noircat.php` + 6 个命名限流器（api / login / register / posts / uploads / search）
- [x] API 骨架：`routes/api.php` + `GET /api/health`

## Phase 1：用户认证（进行中）

### 依赖与数据模型
- [x] Laravel Sanctum 4.3（令牌认证，`personal_access_tokens`）
- [x] spatie/laravel-permission 8.3（`roles` / `permissions` / 关联表）
- [x] `users` 表对齐规格书：新增 `username`（唯一）/ `avatar` / `role`，移除 Laravel 默认的 `name`
      （通过**新增迁移**完成，未改动历史迁移文件）
- [x] `RolesAndPermissionsSeeder`：4 个角色 + 18 个权限点；`DatabaseSeeder` 只播安全基线，**不创建默认账号**

### 接口
- [x] `POST /api/auth/register`（`throttle:register` 3 次/小时/IP）
- [x] `POST /api/auth/login`（`throttle:login` 5 次/分钟 + 50 次/天/IP）
- [x] `GET /api/auth/me`（`auth:sanctum`）
- [x] `POST /api/auth/logout`（吊销当前令牌）
- [x] `PUT /api/auth/profile`（改邮箱需唯一；改密码需 `current_password`，并吊销其它会话）
- [x] `POST /api/auth/avatar`（`throttle:uploads`；仅 jpg/jpeg/png/webp，≤2 MB，禁 SVG）

### 安全实现要点
- [x] 密码经 `Hash` 哈希（模型 `hashed` cast），并支持 `needsRehash` 自动升级
- [x] 登录失败路径做**时序均衡**（不存在的账号也执行一次 bcrypt 校验），且错误信息与状态码完全一致，防账号枚举
- [x] 注册/登录/登出/资料变更/头像变更全部写入 `audit_logs`（密码等敏感字段不入库）
- [x] 头像文件名与扩展名由**检测到的 MIME** 决定，不使用客户端文件名；替换头像时删除旧文件
- [x] 改密码吊销其它令牌，保留当前会话
- [x] 校验失败返回 `422` + `data.errors`；未认证 `401/1001`；限流 `429/5001`

### 测试
- [x] `tests/Feature/Api/AuthTest.php` 18 个用例：注册（含弱密码/非法用户名/重复用户名/重复邮箱）、
      登录（用户名或邮箱、错误密码、防枚举、限流）、me、登出吊销、资料更新、改密码需旧密码、改密码吊销其它令牌、头像上传（含恶意文件拒绝）
- [x] 全量：**40 个用例 / 174 断言通过**；PHPStan level 6 **0 错误**

### 审计产出
- [x] `docs/audit/auth-audit.md`：安全版审计报告（检查清单、实现中发现并修复的 6 个问题、验证过程、复盘）
- [x] `docs/audit/README.md`：审计报告索引
- [x] `vuln-lab` 对照漏洞：见 `docs/audit/auth-vuln-lab.md`

### 待办
- [ ] 邮箱验证与密码重置（含一次性签名链接）
- [ ] 2FA（TOTP）与登录设备列表（可选）
- [ ] 把 18 个权限点接入各模块的 Policy / 路由中间件

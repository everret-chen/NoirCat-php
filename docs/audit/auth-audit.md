# 认证模块审计报告 / Auth module audit

- **范围**：`app/Services/AuthService.php`、`app/Http/Controllers/Api/AuthController.php`、`app/Http/Requests/Auth/*`、`app/Http/Resources/UserResource.php`、`routes/api.php`（auth 部分）、`app/Services/AuditLogService.php`
- **版本**：`dev` 分支 Phase 1（`036b29e` 起）
- **对照漏洞版**：`vuln-lab` 分支同名模块，见 `auth-vuln-lab.md`

## 1. 审计清单与结论

| 检查项 | 结论 | 落点 |
|---|---|---|
| 密码哈希存储 | ✅ bcrypt（模型 `hashed` cast），并支持 `needsRehash` 自动升级 | `AuthService::register` / `authenticate` |
| 账号枚举 | ✅ 统一错误码 `1003` 与相同文案；账号不存在时**同样执行一次 bcrypt** 做时序均衡 | `AuthService::reject` |
| 登录爆破 | ✅ `throttle:login`：5 次/分钟 + 50 次/天/IP | `AppServiceProvider` + `config/noircat.php` |
| 注册滥用 | ✅ `throttle:register`：3 次/小时/IP | 同上 |
| 令牌泄露面 | ✅ 仅注册/登录时返回一次明文令牌，库中存哈希；`/me` 不回传令牌 | Sanctum + `AuthController` |
| 登出是否真正失效 | ✅ 删除当前令牌；实测旧令牌 401，其它会话不受影响 | `AuthService::logout` |
| 改密码后旧会话 | ✅ 吊销除当前令牌外的全部令牌 | `AuthService::updateProfile` |
| 越权改他人资料（IDOR） | ✅ 只操作 `$request->user()`，**不接受客户端传入 `user_id`** | `AuthController::updateProfile` |
| Mass Assignment 提权 | ✅ 注册请求不接受 `role`；`User::$fillable` 受限；角色由服务端 `assignRole` 指定 | `RegisterRequest` / `AuthService::register` |
| 任意文件上传 | ✅ `image` + `mimes:jpg,jpeg,png,webp` + ≤2 MB；**禁 SVG**（可携带脚本）；文件名与扩展名取自检测到的 MIME，不用客户端文件名 | `UploadAvatarRequest` / `AuthService::updateAvatar` |
| 上传残留 | ✅ 替换头像时删除旧文件 | 同上 |
| 审计日志泄露凭据 | ✅ `password/token/secret/authorization/...` 递归脱敏 | `AuditLogService::redact` |
| 错误响应泄露堆栈 | ✅ 仅 `APP_DEBUG=true` 回显细节；生产返回通用文案 + 错误码 | `ApiExceptionRenderer::systemError` |
| CSRF | ✅ API 为无状态令牌认证，不使用 Cookie 会话；Web 路由保留框架 CSRF | `bootstrap/app.php` |
| 用户删除后可追溯 | ✅ `audit_logs.user_id` 为 `nullOnDelete`，payload 中保留账号标识 | `add_profile_columns` / `create_audit_logs` |
| 默认凭据 | ✅ 种子只建角色与权限，**不创建任何账号** | `DatabaseSeeder` |

## 2. 实现过程中发现并修复的问题

| # | 问题 | 风险 | 处理 |
|---|---|---|---|
| 1 | `users` 表是 Laravel 默认结构（`name`），与规格书的 `username/avatar/role` 不符 | 无法按规格实现登录标识与角色 | **新增迁移**补齐字段 + 回填 + 收紧约束 + 删除 `name`（遵守 AGENTS.md「不改历史迁移」） |
| 2 | 用框架 `current_password` 规则会去校验默认 `web` guard | 改密码校验行为不可预期 | 改为在 `UpdateProfileRequest::after()` 内用 `Hash::check` 显式校验，与 guard 解耦 |
| 3 | `where('username')->orWhere('email')` 可能把"用户名恰等于他人邮箱"的账号匹配错 | 登录身份混淆 | 拆成两次查询，**用户名优先** |
| 4 | 账号不存在时直接返回，不消耗计算 | 可通过响应时间枚举账号 | 引入固定 bcrypt 哈希，失败路径时序对齐 |
| 5 | 注册限流 3 次/小时导致同一测试内第 4 个请求返回 429 | 测试误判为功能缺陷 | 拆分用例，每个用例不触碰限流上限 |
| 6 | 单次测试内多次请求会复用已解析的 sanctum guard 用户 | "登出后令牌失效"断言假阳性 | 请求之间 `forgetGuards()`，并断言 `personal_access_tokens` 计数 |

## 3. 验证过程

- `php artisan test`：**40 个用例 / 174 断言全部通过**（其中认证 18 个）
- `vendor/bin/phpstan analyse`（larastan，level 6）：**0 错误**
- 真实 HTTP 冒烟（`php artisan serve --no-reload`）：

```text
注册            201 code=0    role=user roles=user perms=7
弱密码          422 code=3001 errors=password
无令牌 me       401 code=1001 请先登录
带令牌 me       200 code=0
错误密码        401 code=1003 用户名或密码错误
不存在账号      401 code=1003 message 相同=true      ← 防枚举
登录(邮箱)      200 code=0
登出后旧令牌    401 code=1001 ；另一会话仍 200        ← 会话级吊销
```

## 4. 复盘

1. **防枚举 = 错误码 + 文案 + 时序**，只统一文案仍可能被计时区分。
2. **断言也会骗人**：框架在单次测试内复用 guard 状态，安全类断言必须让状态重新走完整路径。
3. **限流既是被测对象也是测试约束**，应当把限流本身作为断言目标，而不是绕过它。
4. **迁移只能新增**：规格演进时用「新增字段 → 回填 → 收紧约束 → 删旧列」四步，保证既有数据可迁移。

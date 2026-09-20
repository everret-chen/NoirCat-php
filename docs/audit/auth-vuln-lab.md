# 认证模块漏洞对照 / Auth (vuln-lab)

> ⚠️ 本分支**故意包含漏洞**，仅用于本地代码审计与渗透练习。
> **禁止**部署公网、**禁止**接入真实数据、**禁止**合并回 `main`。详见 [.github/SECURITY.md](../../.github/SECURITY.md)。

- **基线**：`main` 上已审计的认证模块（含邮箱验证 / 密码重置 / 会话管理），见 [auth-audit.md](auth-audit.md)
- **构造方式**：本分支 = `main` + 一个漏洞提交，共注入 **V1–V11**，每处都带 `// VULN:` 注释便于定位
- **验证方式**：`php artisan test` → 认证部分 **14 个用例由绿转红**（本分支还包含论坛漏洞 V12–V21，全量为 **38 failed / 77 passed**）

## 漏洞清单

| # | 漏洞 | 危害 | 位置 | 复现要点 | main 的防护 |
|---|---|---|---|---|---|
| V1 | 登录/注册无限流 | 口令爆破、批量注册 | `routes/api.php` | 连续 POST `/api/auth/login` 不再返回 429 | `throttle:login`（5/分 + 50/天/IP）、`throttle:register`（3/时/IP） |
| V2 | 登录泄露账号是否存在 | 账号枚举（含时序侧信道） | `AuthService::authenticate` / `reject` | 不存在的账号返回「该账号不存在」、密码错返回「密码错误」；失败路径也不再消耗 bcrypt | 统一错误码 `1003` + 相同文案 + 固定哈希做时序均衡 |
| V3 | 登出不吊销令牌 | 令牌永久有效，登出形同虚设 | `AuthService::logout` | 登出后旧令牌仍可访问 `GET /api/auth/me` | 删除当前令牌（会话级吊销） |
| V4 | 明文密码入库 + 关闭脱敏 | 凭据泄露到日志 / DB 备份 / 运维可见面 | `AuthService::register`、`AuditLogService::redact` | 注册后查 `audit_logs.payload` 可见明文密码 | 敏感字段递归脱敏 |
| V5 | 注册可自选角色 | **直接提权**：注册即管理员 | `AuthService::register`、`RegisterRequest` | 注册请求体带 `"role":"admin"` | 角色由服务端固定为 `user`，请求不接收 `role` |
| V6 | 资料更新 IDOR | 水平越权：改他人邮箱/密码 → 接管账号 | `AuthController::updateProfile`、`UpdateProfileRequest` | 带 `"user_id":<他人ID>` 调 `PUT /api/auth/profile` | 只操作 `$request->user()`，不接受 `user_id` |
| V7 | 任意文件上传 | 上传 `.php` 得到 webshell（经 `public/storage` 访问） | `AuthService::updateAvatar`、`UploadAvatarRequest` | 上传 `shell.php` 成功落盘 | `image` + `mimes:jpg,jpeg,png,webp` + ≤2 MB，文件名/扩展名取自检测到的 MIME |
| V8 | 会话管理越权 | 列出**所有人**的会话；可把**任意用户**踢下线 | `SessionService::list` / `revoke` | `GET /api/auth/sessions` 返回他人会话；`DELETE /api/auth/sessions/{他人令牌ID}` 成功 | 查询经 `$user->tokens()` 作用域，跨用户 ID 结构性返回 404 |
| V9 | 邮箱验证绕过 | 去掉签名与哈希校验后，任何人可构造链接**替他人验证邮箱** | `routes/api.php`、`routes/web.php`、`AuthService::verifyEmail` | 直接访问 `/email/verify/{任意ID}/{任意hash}`（或 API 同名路径）即返回成功 | `signed` 中间件（签名 + 过期）+ `sha1(email)` 哈希比对 |
| V10 | 重置令牌可重用且不过期 | 邮件被转发或日志泄露后，**同一链接可无限次改密码** | `AuthService::resetPassword` | 用同一 token 连续两次调用 `POST /api/auth/password/reset` 都成功 | 框架 broker：一次性消费、60 分钟过期、60 秒节流 |
| V11 | 重置接口无限流 | 重置邮件轰炸（受害者邮箱被刷爆）、重置接口可爆破 | `routes/api.php` | 连续 POST `/api/auth/password/email` 不再返回 429 | `throttle:password_reset` 3 次/小时/IP |

## 被打红的用例（一一对应）

```text
FAILED  AuthTest > login is rate limited per ip                     ← V1
FAILED  AuthTest > login does not reveal whether the account e…     ← V2
FAILED  AuthTest > logout revokes the current token                 ← V3
FAILED  AuthTest > registration writes an audit entry without…      ← V4
FAILED  AuditLogServiceTest > redacts sensitive payload keys…       ← V4
FAILED  AuthTest > registration cannot assign a role                ← V5
FAILED  AuthTest > profile update cannot target another user        ← V6
FAILED  AuthTest > avatar upload accepts images and rejects ot…     ← V7
FAILED  SessionTest > sessions are listed for the caller only       ← V8
FAILED  SessionTest > another users session cannot be revoked       ← V8
FAILED  EmailVerificationTest > a link whose hash does not mat…     ← V9
FAILED  EmailVerificationTest > unsigned and expired links are…     ← V9
FAILED  WebPagesTest > an unsigned verification link is rejected     ← V9（邮件链接改走 Blade 路由后新增）
FAILED  PasswordResetTest > a reset token cannot be used twice      ← V10
FAILED  PasswordResetTest > the reset request endpoint is rate…     ← V11

（认证模块单独统计为 14 例转红；本分支加入论坛漏洞 V12–V21 后全量为 38 failed, 77 passed, 397 assertions）
```

## 三类视角

1. **认证强度**（V1 / V2 / V3）：爆破、枚举、登出无效 —— 都属于「认证环节最容易被自动化攻击」的范畴。
2. **授权与数据边界**（V5 / V6 / V8）：提权、IDOR、越权吊销会话 —— 共同点是**信任了客户端传入的身份信息**（`role` / `user_id` / 令牌 ID）。
3. **数据与文件处理**（V4 / V7 / V9 / V10 / V11）：明文凭据入库、任意文件上传、验证链接可伪造、重置令牌可重放、缺少频次限制。

## 练习建议

1. 用 curl / Burp 把每个漏洞打通，记录完整请求与响应；
2. 回到 `main` 对照实现，写清「为什么这样写就防住了」；
3. 尝试组合利用链：
   - V2 枚举出管理员用户名 → V1 无限流爆破 → V5 直接注册管理员；
   - V2 → V3（登出无效，令牌可长期复用）→ V6 接管既有账号；
   - V4 读审计日志拿明文密码；V10 把邮件里的一次性链接变成长期后门；
   - V9 替受害者完成邮箱验证，绕过依赖 `verified` 中间件的后续模块（Phase 2 起）。

## 覆盖缺口与改进记录

- 最初注入 V5/V6 时**没有任何用例变红** → 说明 `main` 缺少越权类负向用例；随后补上
  `test_registration_cannot_assign_a_role`、`test_profile_update_cannot_target_another_user`，现在它们会红 ✔
- 同理补了 `test_a_reset_token_cannot_be_used_twice`（对应 V10），现在也会红 ✔
- **功能正向测试全绿 ≠ 安全**：越权、可重放、绕过类问题必须专门设计负向用例，CI 才能拦住。

## 安全底线

- 只在本地/隔离环境运行：`php artisan serve --no-reload`
- 不要使用真实邮箱、真实密码或真实数据
- 修复只发生在 `main`；本分支的 `VULN` 注释与提交都不得进入 `main`
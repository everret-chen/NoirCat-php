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

### Phase 1 收尾
- [x] 邮箱验证：`MustVerifyEmail` + 本地化通知 + 签名链接（默认 24 小时）+ 重发接口 + `verified` 中间件（`403/1005`）
- [x] 密码重置：请求/重置接口 + 本地化邮件 + 中性响应（不暴露邮箱是否注册）+ 成功后吊销全部会话
- [x] 会话/设备管理：列表（标记当前会话）/ 吊销单个 / 一键吊销其它（作用域限定本人，跨用户 ID 返回 404）
- [x] 授权接线：显式注册 spatie 中间件别名（v8 不再自动注册），并用测试验证 `permission:…,sanctum` 与 `verified` 行为
- [x] 新增 4 个测试文件（邮箱验证 / 密码重置 / 会话 / 授权接线）；全量 **64 个用例 / 263 断言通过**，PHPStan level 6 **0 错误**
- [x] 端到端冒烟：注册→验证邮件→点击签名链接验证→会话管理→重置密码（**令牌从邮件日志真实提取**）全部通过

### 收尾（已完成）
- [x] `vuln-lab` 对照漏洞：会话 IDOR、验证哈希不校验、重置令牌可重用、重置接口无限流（见 `auth-vuln-lab.md`）
- [x] 邮件改为队列投递（两个通知实现 `ShouldQueue`，本地需 `php artisan queue:work`）
- [x] 前端依赖安装（`npm install`，Tailwind v4 + Vite 已在 `package.json`）
- [ ] 2FA（TOTP）与登录设备信息（可选，暂缓）
- [ ] 把 18 个权限点接入 Phase 2+ 各模块的 Policy 与路由（论坛部分已完成）

## Phase 2：论坛模块（进行中）

### 数据层
- [x] 迁移：`categories`（版块/书类共用，含 `name_en`）、`posts`（Markdown 原文 + `content_html` 缓存、置顶、计数器、软删除）、
      `comments`（嵌套 `parent_id`、状态、软删除）、`likes`（多态 + 唯一约束防重复点赞）
- [x] 模型与工厂：`Category` / `Post` / `Comment` / `Like`；`Post::$fillable` **不含** `author_id` 与 `content_html`
- [x] 种子：5 个论坛版块 + 4 个书类

### 服务与接口
- [x] `MarkdownService`：CommonMark（`html_input=strip`、禁不安全链接）+ HTMLPurifier 白名单，双层过滤；含纯文本摘要
- [x] `PostService`：发帖/改帖（改内容自动重渲染）/删除/置顶/点赞（幂等 + 计数器）/浏览量（每人每帖每小时去重）/列表（版块、作者、排序、搜索）
- [x] `CommentService`：回复（校验父评论属于同一帖 + 深度上限 3 层）、隐藏、删除，事务内维护计数
- [x] 授权：`PostPolicy`（view 支持草稿仅作者/版主可见；update/delete 支持本人或版主；pin 需 `post:pin`）、`CommentPolicy`
- [x] 接口：`GET /api/posts`、`GET /api/posts/{id}`、`POST /api/posts`、`PUT /api/posts/{id}`、`DELETE /api/posts/{id}`、
      `POST /api/posts/{id}/like`、`DELETE /api/posts/{id}/like`、`POST /api/posts/{id}/pin`、
      `GET|POST /api/posts/{id}/comments`、`DELETE /api/comments/{id}`、`POST /api/comments/{id}/hide`、
      `GET /api/categories`、`GET /api/search`
- [x] `UseSanctumGuard` 中间件：让公开 API 路由也能识别 Bearer 令牌（否则策略会把作者当访客）
- [x] 开启 Eloquent 严格模式（非生产环境丢弃非 fillable 字段直接报错）—— 由本次 `content_html` 被静默丢弃的 bug 反推

### 测试
- [x] `MarkdownServiceTest`（6 例）：脚本标签、事件属性、`javascript:`/`data:` 链接、iframe/style 均被清除
- [x] `PostTest`（11 例）：发布与清洗、**作者不可伪造**、草稿不可见、浏览量去重、越权改/删、仅版主置顶、重复点赞不叠加、版块与搜索过滤
- [x] `CommentTest`（7 例）：计数维护、跨帖回复被拒、深度超限被拒、越权删除、版主隐藏、隐藏评论不列出
- [x] `SearchTest`（4 例）：标题与正文命中、**通配符按字面处理**、最小长度、只返回已发布
- [x] 全量：**93 用例 / 367 断言通过**，PHPStan level 6 **0 错误**

### 待办
- [ ] 前端页面（Blade + Tailwind + Alpine）：全局布局、认证页、论坛列表/详情/发帖/评论
- [ ] `docs/audit/forum-audit.md` 与 `vuln-lab` 论坛对照漏洞（存储型 XSS、越权删帖、评论深度绕过等）
- [ ] 搜索切换到 Meilisearch + Scout（当前为参数绑定的 LIKE，已转义通配符）

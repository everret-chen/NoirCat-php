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

### 前端页面（Blade + Tailwind v4 + Alpine）
- [x] 布局与样式：`layouts/app.blade.php`（导航/语言切换/登录态）、`resources/css/app.css` 的 `ink`/`frost` 主题与组件类、Alpine 交互（`resources/js/app.js`）
- [x] 页面：首页、论坛列表（版块/排序/搜索/我的帖子）、帖子详情（Markdown 正文 + 嵌套评论 + 点赞）、发帖/编辑页、登录/注册、个人资料（含头像上传与验证邮箱提示）、会话管理
- [x] `Web\{Home,Auth,Profile,Session,Forum}Controller`：控制器瘦，复用与 API 完全相同的 Service/Request/Policy，双入口行为一致
- [x] 会话认证 + CSRF（`routes/web.php`），登录后 `session()->regenerate()` 防会话固定
- [x] 邮件验证链接改为指向 Web 页面（`verification.verify` 归属 Blade 路由），点开是页面而非 JSON
- [x] 未验证邮箱访问写接口：Web 跳转个人页并提示，API 返回 `403/1005`
- [x] `@vite` 在未构建前端资源时降级（页面仍可打开），构建命令见 README

### 测试
- [x] `MarkdownServiceTest`（6 例）：脚本标签、事件属性、`javascript:`/`data:` 链接、iframe/style 均被清除
- [x] `PostTest`（11 例）：发布与清洗、**作者不可伪造**、草稿不可见、浏览量去重、越权改/删、仅版主置顶、重复点赞不叠加、版块与搜索过滤
- [x] `CommentTest`（10 例）：计数维护、跨帖回复被拒、深度超限被拒、越权删除、版主隐藏、隐藏评论不列出、**草稿不可评论/不可读**、删除与隐藏后计数校正
- [x] `SearchTest`（5 例）：标题与正文命中、**通配符按字面处理且仍可命中**、最小长度、只返回已发布
- [x] `WebPagesTest`（20 例）：页面渲染、访客重定向、注册/登录/登出、签名链接验证、未验证禁写、发帖改帖删帖越权、评论纯文本、点赞切换、资料与会话页、语言切换
- [x] 全量：**115 用例 / 463 断言通过**，PHPStan level 6 **0 错误**

### 审计与修复（本轮）
- [x] `docs/audit/forum-audit.md`：检查清单、9 个发现并修复的问题、验证过程（含缺陷复现数据与修复后对比）、残留风险、复盘
- [x] 修复：草稿可被灌评论 / 草稿评论串可被读（新增 `PostPolicy::comment` + 双入口 `authorize('view')`）
- [x] 修复：评论删除/隐藏后 `comment_count` 虚高
- [x] 修复：SQLite 下 LIKE 通配符转义失效导致 `%`/`_`/`\` 关键词搜不到（改显式 `ESCAPE '!'`）
- [x] 修复：`verified` 中间件从未接线（论坛写接口全部挂上，删除/置顶除外）
- [x] 修复：邮件验证链接点开是 JSON（改为 302 跳页面）
- [x] 清理：删除框架脚手架示例测试（`tests/{Feature,Unit}/ExampleTest.php`，与真实用例重复且无 `RefreshDatabase`）
- [x] 真实 HTTP 端到端冒烟（Cookie + CSRF + 签名链接，共 52 项断言）全部通过
- [x] `vuln-lab` 论坛对照漏洞：见 `docs/audit/forum-vuln-lab.md`

### 邮箱验证可用的本地闭环（本轮）
- [x] 定位"注册后收不到验证邮件"的根因：邮件是 `ShouldQueue` + `QUEUE_CONNECTION=database`，
      没跑 `queue:work` 时只会堆在 `jobs` 表里（实测积压 12 封、`attempts=0`），日志里当然一条都没有
- [x] `php artisan noircat:mail:latest`：直接从邮件日志里提取最新的验证/重置链接（默认 3 条，新的在前），
      解析逻辑抽到 `App\Services\MailLogReader`（处理 HTML 实体、正文/HTML 重复、quoted-printable 软换行，
      且不误伤 `expires=1789…` 这种"看起来像转义"的字面等号）；顺带提示 `jobs` 表还有多少封未投递
- [x] `php artisan noircat:user:create {email} --username= --password= --role= --unverified`：
      命令行建号（首个管理员入口），默认**已验证**、写审计 `auth.user.created`、密码省略时随机生成并只打印一次
- [x] 本地门槛开关：`NOIRCAT_REQUIRE_VERIFIED_EMAIL=false` 可关闭论坛写接口的邮箱验证要求；
      **生产环境忽略该开关**（`EnsureEmailIsVerified` 在 production 下恒为强制）
- [x] 新增测试：`NoircatCommandsTest`（6 例）、`VerifiedGateTest`（4 例，含"生产忽略开关"）、`MailLogReaderTest`（6 例）
- [x] README 补"收不到验证邮件怎么办"三步排查与 Mailpit 接法

### Phase 2.5：治理闭环（本轮）
- [x] 数据层：`posts` 新增 `is_featured` / `is_locked`；新建 `reports` 表（多态目标 + 唯一索引防重复举报 + 处置字段）
- [x] 权限点：`post:feature` / `post:lock` / `post:move` / `report:create`（共 22 个），版主矩阵同步更新
- [x] 服务层：`ModerationService`（置顶/加精/锁定/移版/恢复软删除/回收站列表）、`ReportService`（举报创建、队列、结案与忽略）
- [x] 授权：`PostPolicy` 增加 feature/lock/move/restore，`PostPolicy::comment` 增加锁定判断；新增 `ReportPolicy`；`CommentPolicy` 增加 unhide/restore/report
- [x] 评论生命周期：`CommentService::unhide/restore` 同步维护 `comment_count`；锁定帖在服务层也拒绝新评论
- [x] API：`/api/posts/{id}/{feature,lock,category,restore}`、`/api/comments/{id}/{unhide,restore}`、`/api/moderation/trash`、`/api/reports`（创建 / 队列 / resolve / dismiss）
- [x] Web：帖子版主工具条、评论行内隐藏/取消隐藏/删除/恢复、举报弹层（含"已举报"状态）、治理台（队列 + 状态/原因筛选 + 结案/忽略）、回收站（帖子/评论分列 + 恢复）、导航入口（带待处理计数）
- [x] 修复：Web 页面上的业务规则冲突原本返回 500，现改为跳回并提示（API 仍用信封）
- [x] i18n：`lang/{zh_CN,en}/moderation_ui.php` + `forum.php` 的举报原因/状态/错误文案 + `ui.status.featured|locked`
- [x] 测试：`ModerationTest`（10 例）、`ReportTest`（12 例）、`ModerationPagesTest`（20 例）；全量 **173 用例 / 696 断言通过**，PHPStan level 6 **0 错误**
- [x] 审计文档：`forum-audit.md` 第 6–7 节（治理清单 + 5 个修复）
- [x] `vuln-lab` 治理类对照漏洞 V22–V26（见 `forum-vuln-lab.md`）

### Phase 3 前置：MySQL 8 接线
- [x] 可移植性审计（只读取证，逐文件）：见 `docs/audit/mysql-portability.md`
- [x] 修复：长正文列 `TEXT` → `MEDIUMTEXT`（原校验 5 万字符在 MySQL 会触发 1406）
- [x] 修复：路由 id 参数统一 `[0-9]+`（否则 MySQL 会把 `12abc` 强转成 12 并返回别的资源）
- [x] 修复：所有排序补 `id` 兜底（并列行顺序在两引擎不同 → 分页重复或漏行）
- [x] 配置：`DB_ENGINE=InnoDB`、`DB_TIMEZONE=+08:00`、默认排序规则改 `utf8mb4_0900_ai_ci`，`.env.example` 写明原因
- [x] CI：新增 `Tests against MySQL 8` 任务（MySQL 8 service + `migrate:fresh --seed` + 全量测试 + 表数量断言）
- [ ] **本机真实验证**（需你操作）：启用 `E:\php83\php.ini` 的 `pdo_mysql`；准备 MySQL 8（本机无 CLI / 无 Docker / 3306 未监听）；然后 `migrate:fresh --seed` + `php artisan test`

### 待办
- [ ] 搜索切换到 Meilisearch + Scout（当前为参数绑定的 LIKE，通配符已按字面转义）
- [ ] @提及与站内通知（M6：被回复 / 被@ / 举报处置结果）
- [ ] 评论点赞（`comments.like_count` 字段已建，尚无接口）
- [ ] 邮箱硬化（已取证，未修）：
      - 改邮箱后 `email_verified_at` 不失效 → 验证一次后换任意邮箱仍算已验证（`AuthService::updateProfile`）
      - 邮箱未规范化：`A@x.com` 与 `a@x.com` 可注册成两个账号，且用邮箱登录时大小写敏感
      - `email_verification` 只有每账号 3 次/分钟，缺每日上限与队列去重

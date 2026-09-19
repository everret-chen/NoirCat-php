# 墨猫问雪 / NoirCat — PHP 版方案 v2

> 基线：`PROJECT_SPEC_PHP.md`（v1，未改动）
> 性质：架构说明 + 方案改进。自包含，确认后可直接替换 v1 成为 v2 规格书。
> 约定：遵循 `AGENTS.md`（PHP 8.3+ / PSR-12 / 2 空格 / 控制器瘦服务层厚 / API 响应 `{code,message,data}` / 中文沟通 / 英文注释）。

---

## 0. 先回答"我理解的是什么"

### 0.1 一句话定位

一个**中英双语**的网络安全学习社区：用「电子书 + 论坛 + 学习路线」留住学习者，用「公会 + 插件」把社区组织起来，再把**项目自身**做成 PHP 代码审计的靶场（`main` 安全版 / `vuln-lab` 漏洞版双分支）。

### 0.2 架构分层

```text
                浏览器 (Blade + Tailwind + Alpine.js)
                            │
        ┌───────────────────┴───────────────────┐
        │ HTTP                                  │ WebSocket (Reverb)
   routes/web.php                          routes/api.php
   （会话 + CSRF，出页面）                  （Sanctum，出 JSON /api/*）
        └───────────────────┬───────────────────┘
                            │
        Middleware：auth:sanctum · throttle · locale · verified · csrf
                            │
        Http/Controllers（瘦：只做校验与调用）
             │  FormRequest（校验 + 授权）
             ▼
        app/Services（厚：全部业务逻辑、事务、外部调用）
             │
             ├── Models (Eloquent) → MySQL
             ├── Policies（授权）· spatie/laravel-permission（角色）
             ├── Events / Listeners（发帖 → 索引、通知、积分、审计日志）
             ├── Jobs / Queue（全文索引、通知投递、文件处理）
             └── app/Plugins/**（公会插件沙箱：PluginContext）
                            │
   外部能力（全部走 Laravel 抽象，随时可替换）：
   Redis(缓存/队列/在线状态) · MinIO(Filesystem) · Meilisearch(Scout) · Reverb(广播)
```

### 0.3 四条产品主线与技术落点

| 模块 | 用户价值 | 技术要点 | 审计价值（vuln-lab） |
|---|---|---|---|
| 电子书 | 资源聚合与在线阅读 | 上传校验、对象存储预签名 URL、流式下载、格式解析 | 任意文件上传、路径穿越、SSRF、越权下载 |
| 博客论坛 | 内容沉淀与讨论 | Markdown + HTML Purifier、嵌套评论、全文搜索 | 存储型 XSS、IDOR、CSRF、SQLi |
| 学习成长 | 路线化、可量化 | 路线→阶段→条目、进度、测验评分 | 业务逻辑漏洞（刷分/跳步）、越权改他人进度 |
| 公会系统 | 组织化 + 实时互动 | 成员角色、积分、聊天室、**插件系统** | 垂直/水平越权、插件沙箱逃逸、积分竞态 |
| 认证与权限 | 账号体系 | Sanctum + spatie 权限 | 爆破、会话固定、JWT 算法混淆、提权 |

### 0.4 贯穿全站的设计原则

1. **控制器瘦、服务层厚**：业务逻辑集中在 `app/Services`，便于单测与审计（审计时只看 Service 层就能覆盖 80% 危险面）。
2. **双层出入口共用一套服务**：`web.php` 与 `api.php` 都调用同一 Service，避免"页面安全、接口有洞"的经典问题（这本身也是审计考点）。
3. **事件驱动解耦**：发帖/上传/加公会只发事件，索引、通知、积分、审计日志各自监听，互不阻塞。
4. **一切外部依赖走抽象**：`Storage` / `Scout` / `Cache` / `Queue` / `Broadcast`。本地开发可以零依赖跑通，生产换驱动不改代码。
5. **插件只给能力、不给权限**：插件拿到的是受控代理对象，不是 `DB::` 与文件系统。

---

## 1. 功能清单（按模块，含验收标准）

> 每模块完成定义（DoD）：功能可用 + Pest 测试 + PHPStan 通过 + `main` 审计报告 + `vuln-lab` 对照漏洞 + 更新 `docs/phases.md`。

### M0 地基（Phase 0）
- 中英双语 i18n：`lang/{zh_CN,en}`，`__()` 全覆盖，URL/Content-Language 语言切换，日期/数字本地化。
- 统一 API 响应 `{code,message,data}`、统一异常处理（`Handler` 里映射业务异常 → 状态码）。
- 分页规范 `?page=&limit=` → 响应含 `total/page/limit`（实现为 Service 层统一 Trait）。
- 操作审计日志（谁、何时、对什么、做了什么、结果），安全项目必备。
- 限流矩阵（见 3.3）。

### M1 认证与用户（Phase 1）
- 注册 / 登录 / 登出 / 令牌管理（Sanctum）；邮箱唯一 + 用户名唯一（大小写与保留字处理）。
- 密码：`Hash::make`（bcrypt 或 Argon2id）、强度校验、重置流程（带签名的一次性链接）。
- 角色权限：`user / guild_admin / moderator / admin`（spatie），权限点而非角色硬编码判断。
- 个人资料：头像上传（MinIO，限制类型/尺寸）、签名/简介、隐私开关。
- 可选（教学价值高）：2FA（TOTP）、登录设备列表、异地登录提醒。

### M2 论坛（Phase 2）
- 版块（categories，见 2.1）；发帖/编辑/删除（软删除 + 回收站）；草稿/发布状态。
- Markdown：`league/commonmark` 渲染 → **HTML Purifier 白名单过滤** → 存 `content_html`。
- 嵌套评论（`parent_id`，限制层数）、点赞、浏览量（防刷：Redis/去重窗口）、@提及。
- 举报 → 版主处理；版主操作：置顶/加精/锁定/移版。
- 全文搜索：Meilisearch（中文分词）+ Scout，双语言索引策略见 3.5。

### M3 电子书（Phase 3）
- 上传：扩展名 + MIME + 文件头三重校验，大小限制，**禁止可执行/脚本**；文件哈希去重。
- 存储：MinIO 私有桶，下载/阅读用**临时预签名 URL**，不暴露真实路径。
- 元数据：标题/作者/简介/封面/格式(epub/pdf/mobi/txt)/分类/标签；评分与评论。
- 在线阅读：epub.js / pdf.js 前端渲染（PDF 走服务端流式，避免直链）。
- 下载计数：队列异步 + 去重（同一用户/IP 时间窗内只记一次）。

### M4 学习成长（Phase 4）
- 路线（`learning_paths`）→ **阶段（`learning_stages`，v1 缺失）** → 条目（`learning_items`：article/video/exercise/quiz）。
- 进度：`user_progress`（not_started / in_progress / completed + score + 时间戳）。
- 测验：题目/选项/答案/解析，服务端判分（答案不下发前端），限次与冷却。
- 成就/徽章（可选）：完成路线、全勤、发帖里程碑。

### M5 公会（Phase 5）
- 创建（消耗积分/等级门槛）/ 加入（申请-审批）/ 退出 / 解散；成员上限 `max_members`。
- 成员角色：owner / vice_leader / elder / member + 权限点；贡献度与积分、公会等级。
- 聊天室：Reverb 广播 + `guild_messages` 持久化；敏感词、撤回、禁言。
- 公会公告、成员日志（谁踢了谁、谁发了公告 → `audit_logs`）。

### M6 通知
- 站内通知（`notifications` 表）+ Reverb 实时推送 + 邮件（队列）；免打扰与订阅设置。

### M7 插件系统
- 见第 4 节。示例插件：每日签到（对应 v1 第 5 节）。

---

## 2. 数据模型修正（v1 → v2）

### 2.1 v1 的三处缺口（必须补）

| # | 问题 | 影响 | v2 决策 |
|---|---|---|---|
| 1 | 认证口径冲突：第 1/9 节写 **Sanctum**，第 4 节写 **JWT Bearer** | 实现方式完全不同 | 统一为 **Sanctum**（令牌天然走 `Authorization: Bearer`，同时支持 SPA cookie）。JWT 手写版留作 `vuln-lab` 的审计靶子（算法混淆/`alg=none`/弱密钥），一举两得 |
| 2 | `learning_items.stage_id` 无对应表 | 外键无法建立，层级断链 | 新增 `learning_stages(id, path_id, title, sort_order, ...)`，`learning_items.stage_id → learning_stages.id` |
| 3 | `books.category_id` / `posts.category_id` 无 `categories` 表 | 同上 | 新增 `categories(id, type['book','post'], name, slug, parent_id, sort_order)`；如需多标签再加 `tags` + `taggables` |

### 2.2 建议补充的表（按阶段增量迁移，遵守"不改历史迁移"）

| 表 | 归属 | 要点 |
|---|---|---|
| `personal_access_tokens` | Sanctum 自带 | — |
| `roles` / `permissions` / `model_has_*` | spatie 自带 | 权限点命名 `资源:动作`，如 `post:delete_any` |
| `categories` | Phase 2 | type 区分版块/书类；`slug` 唯一 |
| `tags` + `taggables` | Phase 2 | 多态，论坛与电子书共用 |
| `comments` | Phase 2 | v1 已有；补 `status`（可见/隐藏） |
| `likes` | Phase 2 | 多态 `(user_id, target_type, target_id)` 唯一 |
| `reports`（举报） | Phase 2 | 多态 + 处理状态 + 处理人 |
| `notifications` | Phase 6 | Laravel 通知表 |
| `book_ratings` | Phase 3 | `(user_id, book_id)` 唯一，评分 1–5 |
| `download_logs` | Phase 3 | 计数去重与审计 |
| `learning_stages` | Phase 4 | 见 2.1 |
| `quiz_questions` / `quiz_options` / `quiz_attempts` | Phase 4 | 答案不下发；attempts 限次 |
| `guild_join_requests` | Phase 5 | 申请-审批流 |
| `guild_messages` | Phase 5 | v1 已有；补 `status`（撤回/隐藏） |
| `audit_logs` | Phase 0 | 安全项目的地基，越早越好 |
| `jobs` / `failed_jobs` / `job_batches` | Phase 0 | 队列驱动为 database 时需要 |

### 2.3 表设计要点

- **索引**：所有外键加索引；`posts(status, created_at)`、`books(category_id, created_at)`、`guild_messages(guild_id, id)` 复合索引支撑列表页。
- **软删除**：`posts`/`comments`/`books`/`guilds` 用 `softDeletes()`，配合回收站与恢复权限。
- **评分精度**：`decimal(3,1)` 保留（v1 正确），聚合缓存到 `books.rating` 由事件更新。
- **计数列**（`view_count`/`like_count`/`comment_count`/`download_count`）：写入走原子自增，避免并发丢更新（`increment()` 生成 `set x = x + 1`）。
- **时间**：统一 `timestamps()`，展示层按用户时区转换；`joined_at`/`installed_at` 用 `useCurrent()`（v1 正确）。
- **i18n 内容**：帖子的多语言字段建议放 `posts.translations` JSON 或后续做 `post_translations` 表，**Phase 2 先不做**，避免过度设计。

---

## 3. API 规范补充（v1 只有骨架）

### 3.1 认证端点（v1 缺失）
```text
POST   /api/auth/register        注册（返回 token + user）
POST   /api/auth/login           登录
POST   /api/auth/logout          注销当前令牌
GET    /api/auth/me              当前用户 + 权限点
PUT    /api/auth/profile         更新资料
POST   /api/auth/avatar          上传头像
POST   /api/auth/password/email  发送重置链接
POST   /api/auth/password/reset  重置密码
```

### 3.2 统一响应
- 成功：直接返回资源或 `{ data, meta }`（分页放 `meta`）。
- 失败：`{ "code": 400, "message": "...", "data": null }`（v1 已定义），`message` 走 i18n。
- 业务错误码段位：`1xxx` 认证、`2xxx` 权限、`3xxx` 校验、`4xxx` 业务规则、`5xxx` 系统。

### 3.3 限流矩阵（安全关键）
| 端点 | 限制 | 说明 |
|---|---|---|
| 登录 / 重置密码 | 5 次/分钟/IP + 失败锁定 | 防爆破 |
| 注册 | 3 次/小时/IP | 防批量注册 |
| 发帖 / 评论 | 10 次/分钟/用户 | 防灌水 |
| 上传 | 20 次/小时/用户 | 防资源滥用 |
| 搜索 | 60 次/分钟/IP | 防爬与压测 |
| 全部 API | 120 次/分钟/用户兜底 | 全局 throttle |

### 3.4 其他
- 分页默认 `limit=20`，上限 100；排序白名单（禁止直接透传 `orderBy` 字段名）。
- 列表过滤参数白名单（防参数污染与隐式 SQL 注入）。
- 写接口幂等：上传与积分类操作用幂等键或唯一索引兜底。
- 静态分析：`docs/api/openapi.yaml` 可选，Phase 3 之后再补。

### 3.5 双语言搜索
中英双语内容检索建议 Meilisearch **双索引**（`posts_zh` / `posts_en`）或单索引 + `locale` 可过滤属性；中文分词依赖 Meilisearch 内置 CJK 支持，索引在队列任务里更新。

---

## 4. 插件系统设计（v1 加强版）

### 4.1 目录与 Manifest
```text
app/Plugins/DailyCheckin/
├── manifest.json      # id/name/version/entry/permissions/hooks/config
└── Plugin.php         # activate() / deactivate()
```
Manifest 沿用 v1 字段，新增建议：`min_app_version`、`author`、`homepage`、`requires`（依赖的其他插件）。

### 4.2 PluginContext 能力白名单
| 能力 | 说明 | 禁止事项 |
|---|---|---|
| `$ctx->db` | 只读/受限 Query Builder 代理：**强制参数绑定**、表名前缀校验、禁止原始 `DB::statement` | 不得使用 `DB::` facade、不得执行 DDL |
| `$ctx->router` | 注册 `guilds/{id}/plugins/{plugin}/...` 子路由 | 不得注册全局路由、不得绕过中间件 |
| `$ctx->schedule` | 注册定时任务 | 不得注册系统级 cron |
| `$ctx->on/emit` | 事件总线（Laravel Event 封装） | 不得监听核心安全事件（如登录成功）以外未授权事件 |
| `$ctx->services` | 注册/调用受控服务（如 `guildPoints`） | 不得直接改 `guilds.points` 表 |
| `$ctx->config` | 按 manifest schema 读写自己的配置 | 不得读写其它插件配置 |
| `$ctx->storage` | 插件私有目录（Filesystem 抽象） | 不得访问应用根目录与 `.env` |

### 4.3 真正"沙"住插件的三层
1. **能力代理层**（PHP 层）：`PluginContext` 只暴露白名单方法，插件拿不到容器与 facade。
2. **数据层**：插件数据库操作用独立连接（受限账号，仅授予必要表权限），或全部经 `PluginDbProxy` 强制绑定参数 + 表名白名单。
3. **静态检查**：Semgrep 规则扫描插件目录里的 `DB::`、`exec/system/shell_exec`、`file_get_contents`、`eval`、`unserialize` 等危险函数，CI 阻断。

### 4.4 权限与生命周期
- 权限点形如 `guild:member:read`、`guild:points:write`，安装时向公会管理员展示并确认。
- 生命周期：`install`（校验 manifest）→ `enable`（activate，注册路由/任务/监听）→ `disable`（deactivate + `cleanup()`）→ `uninstall`（清配置，数据默认保留并提示）。
- 失败隔离：插件 activate 抛异常 → 只禁用该插件，不影响公会与全站。
- 审计点：`vuln-lab` 里做**沙箱逃逸**（通过未过滤的参数绕到 `DB::raw`、路径穿越读写文件、事件总线提权）并写报告。

---

## 5. 本机环境现实与落地路径（重要，v1 没考虑）

### 5.1 现状（已实测）
| 项 | 状态 |
|---|---|
| Docker | ❌ 未安装（`docker` 命令不存在） |
| PHP | ❌ 不在 PATH；`E:\phpstudy` 只有 **PHP 5.2–7.2**，**跑不了 Laravel 11/12**（需 8.2+/8.3+） |
| Composer | ❌ 未安装 |
| MySQL | ❌ 不在 PATH；phpstudy 自带 MySQL（2018 版多为 5.5/5.7，版本待确认） |
| Redis | ❌ 无官方 Windows 版（需 Memurai 或替代驱动） |
| 仓库 | `dev`/`main`/`vuln-lab` 三分支已建，但**只提交了 `REAME.md`，Laravel 骨架尚未初始化**（`docs/audit/phases.md` 里"初始化 Laravel 项目"勾选不实，建议改回未完成） |

### 5.2 三条落地路径
- **A｜最快跑通（推荐先走这条）**：装 PHP 8.3/8.4 + Composer → `laravel new` → **SQLite** 作数据库、`CACHE_STORE=database`、`QUEUE_CONNECTION=database`、`SESSION_DRIVER=database`、本地 `storage/app/public` 当文件盘、Scout 用 `database` 驱动。零外部服务即可完成 Phase 0–2。
- **B｜标准环境**：在 A 基础上装 MySQL 8（或确认 phpstudy 的 MySQL ≥ 5.7）、Meilisearch（官方 Windows exe）、MinIO（官方 Windows exe）。Phase 3 之前接上即可。
- **C｜Docker**：愿意装 Docker Desktop + WSL2 的话，直接 `docker compose up -d` 起全套（MySQL/Redis/MinIO/Meilisearch），与 v1 的部署设想一致。

**推荐节奏**：A 先把 Phase 1 认证跑通（今天就能看到页面），随后按 Phase 需要升级到 B；Docker 作为可选，不影响主线。

### 5.3 开发期依赖替换对照
| 生产 | 开发期替代 | 切换方式 |
|---|---|---|
| MySQL 8 | SQLite | `.env` 的 `DB_CONNECTION` |
| Redis | `database` 驱动 | `CACHE_STORE` / `QUEUE_CONNECTION` |
| Meilisearch | Scout `database` 驱动 | `SCOUT_DRIVER` |
| MinIO | 本地 `public` 盘 | `FILESYSTEM_DISK` |
| Reverb | `log`/`null` 广播 | `BROADCAST_CONNECTION` |

---

## 6. 安全审计工作流（v1 加强版）

### 6.1 分支模型
```text
dev  ──功能开发──► main（安全版，只接收已审计的合并）──► vuln-lab（从 main 派生，按模块注入漏洞）
```
- `vuln-lab` **永不合并回 `main`**；每个模块一个漏洞注入提交，附 `docs/audit/{module}-audit.md`。
- 远程已配置：`https://github.com/everret-chen/NoirCat-php.git`（三分支都在）。

### 6.2 每模块漏洞菜单（vuln-lab 备案）
| 模块 | 注入的经典漏洞 |
|---|---|
| 认证 | 无频次限制可爆破、会话固定、弱随机令牌、JWT `alg=none`/弱密钥（若引入自写 JWT）、越权改他人资料（IDOR） |
| 论坛 | 存储型 XSS（绕过 Purifier 白名单）、SQLi（手写 `whereRaw`）、CSRF 缺失、越权删帖、SSRF（外链图片抓取）、SSTI（若引入模板渲染） |
| 电子书 | 任意文件上传（伪装扩展名/MIME）、路径穿越下载、未授权下载、XXE（若解析 epub/xml） |
| 学习 | 刷分与跳步、并发重复提交、答案泄露到前端 |
| 公会 | 垂直/水平越权（普通成员改公会设置）、积分竞态、插件沙箱逃逸、聊天室 XSS |
| 通用 | Mass Assignment（`$guarded = []`）、调试模式泄露（`APP_DEBUG=true` 上线）、`.env` 可通过 Web 访问、日志注入 |

### 6.3 工具链与 CI
- 本地：`vendor/bin/phpstan analyse`（level 6+）、`php artisan test`（Pest）、`composer audit`。
- Semgrep：自定义规则放 `docs/audit/semgrep/*.yml`，重点扫 `eval` / `exec` / `unserialize` / `DB::raw` / `file_get_contents` / 未过滤 `request()->input()`。
- GitHub Actions：push 到 `dev` 时跑 PHPStan + Pest + Composer audit；`vuln-lab` 分支允许失败但要有报告。
- 动态：Burp Suite / OWASP ZAP 对本地实例；对照靶场 DVWA / Pikachu / BWAPP。

### 6.4 报告模板（`docs/audit/{module}-audit.md`）
```markdown
# {模块} 审计报告
## 1. 范围与版本（commit / 分支）
## 2. 发现问题（每条：标题 / 危险等级 / 位置 / 复现步骤 / 原理 / 影响）
## 3. 修复方案（对应 main 的修复提交）
## 4. 验证过程（修复后无法复现的证据）
## 5. 复盘（本模块学到的审计方法论）
```
另建 `docs/audit/README.md` 作为漏洞总索引表。

### 6.5 底线规则
- `vuln-lab` 只在**本地/隔离环境**运行，禁止部署公网、禁止接入真实用户数据。
- 漏洞代码只在 `vuln-lab` 存在；`main` 每个提交都应通过审计清单。

---

## 7. 分阶段计划（对齐 `docs/phases.md`）

| 阶段 | 交付物 | 审计产出 | DoD |
|---|---|---|---|
| **Phase 0** 环境 | PHP 8.3+/Composer/Laravel 骨架/i18n 骨架/统一响应/审计日志/限流 | — | `php artisan serve` 可访问，`/api/health` 200，PHPStan 0 错 |
| **Phase 1** 认证 | 注册/登录/登出/me/资料/头像/Sanctum + spatie 权限 | `auth-audit.md` | 全流程 Pest 通过；vuln-lab 可爆破、可越权改资料 |
| **Phase 2** 论坛 | 版块/发帖/评论/点赞/Markdown+Purifier/搜索 | `forum-audit.md` | XSS 载荷被过滤；vuln-lab 存储型 XSS 可打 |
| **Phase 3** 电子书 | 上传/预签名下载/在线阅读/评分/分类标签 | `books-audit.md` | 恶意文件被拒；vuln-lab 可传 webshell |
| **Phase 4** 学习 | 路线/阶段/条目/进度/测验/成就 | `learn-audit.md` | 答案不下发；vuln-lab 可刷分 |
| **Phase 5** 公会+插件 | 公会/成员/聊天室(Reverb)/插件框架/签到插件 | `guild-audit.md`、`plugin-sandbox-audit.md` | 插件越权被拦；vuln-lab 可沙箱逃逸 |

每阶段结束更新 `docs/phases.md`（AGENTS.md 要求）。

---

## 8. 待你拍板的 5 个决策

1. **认证**：统一用 **Sanctum**（推荐，省事且安全）；JWT 手写版仅作为 `vuln-lab` 审计靶子。是否同意？
2. **PHP 安装方式**：官方 zip（手动配 PATH）／`winget install PHP.PHP.8.3`／换 Laragon 集成环境（自带 PHP 8.3 + MySQL 8 + Redis 友好）。你机器已有 phpstudy，但版本太老。
3. **开发期数据库**：先 **SQLite** 跑通 Phase 1（推荐），还是直接上 MySQL 8？
4. **双语言 i18n**：从 Phase 1 就做（推荐，后补成本高），还是先把中文做出来再补英文？
5. **Docker**：现在装（Phase 3 前用得上 Redis/MinIO/Meilisearch），还是先走 SQLite 路线、后面再说？

---

## 9. 我建议的下一步（等你确认后执行）

1. 装 PHP 8.3+ 与 Composer（决策 2）。
2. 在 `F:\NoirCat-php` 用 composer 创建 Laravel 骨架，`.gitignore` 排除 `.env`/`vendor`（AGENTS.md 要求）。
3. 建 Phase 0 地基：i18n 骨架、统一响应 Trait、异常处理、`audit_logs` 表与限流矩阵。
4. 把本文件合并进 `PROJECT_SPEC_PHP.md` 成为 v2，并修正 `docs/audit/phases.md` 里不实的勾选。

---

## 10. 决策记录（2026-09）

| 决策项 | 结论 |
|---|---|
| 认证 | 统一 **Laravel Sanctum**；手写 JWT 仅作为 `vuln-lab` 审计靶子 |
| PHP | **官方 zip 手动配 PATH** → 已装 PHP 8.3.33 NTS x64 于 `E:\php83` |
| 开发期数据库 | **SQLite**（`database/database.sqlite`）+ `SESSION/QUEUE/CACHE=database`，先跑通 Phase 1 |
| i18n | **从 Phase 1 起就做**（`APP_LOCALE=zh_CN`、`APP_FALLBACK_LOCALE=en`） |
| Docker | **暂缓**，等 Phase 3（需要 Redis/MinIO/Meilisearch 时）再装 |
| Composer 源 | 官方 GitHub 被 hosts 拦截 → 使用**腾讯云镜像** `https://mirrors.tencent.com/composer/` |
| 时区 | `APP_TIMEZONE=Asia/Shanghai`（`config/app.php` 的 timezone 已改为读该变量） |

### 环境注意事项（本机）
- 本机 `hosts` 将 `github.com` / `api.github.com` / `raw.githubusercontent.com` 等指向 `127.0.0.1`，由 **Watt Toolkit（SteamTools CA）** 做本地中间人：`git push` 依赖其运行；PHP 不信任该 CA，故 Composer 不能走 GitHub。
- 本机无 Docker、无 WSL 发行版（Windows Home 版），装 Docker Desktop 需管理员 + WSL2 + 重启。
- 当前为 Windows 沙箱环境，写工作区外文件需要提权。

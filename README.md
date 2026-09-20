# 墨猫问雪 · NoirCat

> 以问为刃，以雪为盟。
> Ask the snow, secure the code.

中英双语网络安全学习社区 | Cybersecurity Learning Community

## 模块

- 📚 电子书资源 / Books & Tools
- 💬 博客论坛 / Forum & Blog
- 🛡️ 公会系统 / Guild & Plugins
- 🎓 学习成长 / Learning Paths

## 技术栈

- PHP 8.3 + Laravel 12
- Blade + Tailwind CSS + Alpine.js
- 生产：MySQL 8 + Redis + MinIO + Meilisearch；开发期：SQLite + `database` 驱动
- 认证 Laravel Sanctum，权限 spatie/laravel-permission
- 实时 Laravel Reverb（WebSocket）

## 本地开发

要求：PHP 8.3+、Composer 2（Windows 下需把 PHP 目录加入 PATH）。

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed   # 建表 + 角色/权限 + 论坛版块
php artisan storage:link     # 头像与上传文件的公开访问
php artisan serve            # http://localhost:8000
```

验证邮件与重置邮件是**队列投递**的（`ShouldQueue` + `QUEUE_CONNECTION=database`），
本地调试时另开一个终端跑 worker：

```bash
php artisan queue:work
```

### 收不到验证邮件？

按这个顺序排查（**没跑 worker 是头号原因**：邮件只会躺在 `jobs` 表里，哪儿都不会出现）：

1. 先把积压的邮件投递出去：

   ```bash
   php artisan queue:work --stop-when-empty   # 或在另一个终端常驻 queue:work
   ```

2. 直接把最新的验证 / 重置链接打印出来（本地 `MAIL_MAILER=log`，信件落在 `storage/logs/laravel.log`）：

   ```bash
   php artisan noircat:mail:latest            # 打印最近 3 条，点开即可验证
   ```

   命令会顺带提醒 `jobs` 表里还有多少封没投递。

3. 想要"真的在邮箱里看到信"：本机装 [Mailpit](https://mailpit.axllent.org/)（单文件可执行，**不需要 Docker**），
   然后把 `.env` 改成：

   ```ini
   MAIL_MAILER=smtp
   MAIL_HOST=127.0.0.1
   MAIL_PORT=1025      # Mailpit 的 SMTP 端口
   ```

   收件箱 UI 在 `http://127.0.0.1:8025`。要用真实邮箱（QQ / Gmail 授权码、企业邮、云邮件推送），
   把上面的 SMTP 换成对应的 host / port / 用户名 / 授权码即可，**凭据只写 `.env`，不要提交**。

### 首个管理员账号

种子数据只建角色与权限，**不含任何默认账号**（安全基线）。第一个管理员用命令创建：

```bash
php artisan noircat:user:create admin@noircat.local --username=admin --role=admin
# 密码自动生成并只打印一次；--unverified 可保留未验证状态
# --role=user|moderator|guild_admin|admin
```

控制台创建的账号由操作者背书邮箱真实性，因此**默认标记为已验证**，可以直接发帖、置顶。

### 本地关掉邮箱验证门槛

论坛写接口默认要求邮箱已验证（API `403/1005`，网页跳 `/profile` 提示）。
本机没有邮件通道时，可以在 `.env` 里关掉：

```ini
NOIRCAT_REQUIRE_VERIFIED_EMAIL=false
```

**生产环境忽略这个开关**：`APP_ENV=production` 时 `verified` 中间件始终生效，
避免复制 `.env` 时把门槛一起带上线（`config/noircat.php` 的 `auth.require_verified_email`）。

前端资源（Tailwind v4 + Vite）：

```bash
npm install
npm run dev        # 开发时热更新
npm run build      # 部署前构建
```

开发期数据库为 SQLite（`database/database.sqlite`），会话、队列、缓存均用 `database` 驱动，
**不需要** Docker / MySQL / Redis 即可跑通；后续 Phase 接入 MySQL、Redis、MinIO、Meilisearch 时只改 `.env`。

## 页面（Blade）

| 路径 | 说明 |
|---|---|
| `/` | 首页：站点介绍与最新帖子 |
| `/forum` | 论坛列表：版块、排序、搜索、`?mine=1` 只看自己的帖子 |
| `/forum/{id}` | 帖子详情：Markdown 正文、嵌套评论、点赞 |
| `/forum/create`、`/forum/{id}/edit` | 发帖 / 编辑（需登录且**邮箱已验证**） |
| `/login`、`/register` | 登录 / 注册 |
| `/profile` | 个人资料、头像上传、邮箱验证提示与重发 |
| `/sessions` | 会话（设备）管理 |

未验证邮箱的账号可以浏览，但发帖/评论/点赞会被引导到 `/profile`（API 对应 `403/1005`）。
邮件里的验证链接指向 `GET /email/verify/{id}/{hash}`（签名 + 邮箱哈希校验），点开后会跳回页面并提示结果。

## 目录约定

```text
app/Http/Controllers/   控制器（瘦，只做校验与调用）
app/Models/             Eloquent 模型
app/Services/           业务逻辑（厚）
app/Policies/           授权策略
app/Plugins/            公会插件系统
app/Providers/          服务提供者
database/migrations/    数据库迁移（历史文件不修改）
resources/views/        Blade 视图
routes/web.php          页面路由
routes/api.php          API 路由（/api/*）
tests/                  Pest / PHPUnit 测试
docs/                   方案、阶段记录与审计报告
```

## 文档

- `PROJECT_SPEC_PHP.md` — 方案书 v1
- `docs/PLAN_V2.md` — 架构说明与方案改进 v2
- `docs/audit/README.md` — 审计报告索引（含 `vuln-lab` 对照漏洞）
- `docs/audit/phases.md` — 开发记录
- `AGENTS.md` — 协作与代码约定

## 分支策略

- `main` — 安全版（正常修复漏洞）
- `vuln-lab` — 漏洞版（故意保留经典漏洞，用于代码审计与渗透练习）
- `dev` — 日常开发

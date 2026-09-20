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

前端资源（Tailwind v4 + Vite）：

```bash
npm install
npm run dev        # 开发时热更新
npm run build      # 部署前构建
```

开发期数据库为 SQLite（`database/database.sqlite`），会话、队列、缓存均用 `database` 驱动，
**不需要** Docker / MySQL / Redis 即可跑通；后续 Phase 接入 MySQL、Redis、MinIO、Meilisearch 时只改 `.env`。

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
- `docs/audit/phases.md` — 开发记录
- `AGENTS.md` — 协作与代码约定

## 分支策略

- `main` — 安全版（正常修复漏洞）
- `vuln-lab` — 漏洞版（故意保留经典漏洞，用于代码审计与渗透练习）
- `dev` — 日常开发

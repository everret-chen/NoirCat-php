# 墨猫问雪 / NoirCat — 完整项目方案

> 版本：v1.0  
> 更新日期：2026-09-19  
> 维护人：项目负责人（你）  
> 说明：本文档整合了项目方案书、开发路线图、DSH 提示词、协作规范，供开发与审计参考。

---

## 目录

1. [项目概览](#1-项目概览)
2. [技术栈](#2-技术栈)
3. [项目目录结构](#3-项目目录结构)
4. [数据库核心表](#4-数据库核心表)
5. [API 设计规范](#5-api-设计规范)
6. [公会插件系统](#6-公会插件系统)
7. [安全审计要求](#7-安全审计要求)
8. [开发约定](#8-开发约定)
9. [开发优先级](#9-开发优先级)
10. [安全要求](#10-安全要求)
11. [开发路线图（人看的版本）](#11-开发路线图人看的版本)
12. [DSH 提示词汇总](#12-dsh-提示词汇总)
13. [AGENTS.md 模板](#13-agentsmd-模板)
14. [常用命令速查](#14-常用命令速查)
15. [学习资源](#15-学习资源)
16. [三条铁律](#16-三条铁律)
17. [当前进度](#17-当前进度)

---

## 1. 项目概览

- **项目名**：墨猫问雪 / NoirCat
- **定位**：中英双语网络安全学习社区
- **核心模块**：电子书资源 / 博客论坛 / 公会系统 / 学习成长
- **设计理念**：核心精简，功能插件化
- **开发目标**：巩固 PHP，同时提升代码审计与安全开发能力

**用户能做什么**：

- 📚 下载电子书、脚本工具，在线阅读
- 💬 发帖、写博客、讨论技术（支持 Markdown）
- 🛡️ 创建或加入公会，聊天，装插件（签到、成就）
- 🎓 按学习路线学计算机与网络安全，记录进度

**用户角色**：

| 角色 | 权限 |
|------|------|
| 游客 | 浏览公开内容 |
| 注册用户 | 发帖、下书、加公会、学路线 |
| 公会管理员 | 管成员、装插件 |
| 管理员 | 管全站 |

---

## 2. 技术栈

| 层级 | 技术 | 说明 |
|------|------|------|
| 语言 | PHP 8.3+ | 类型声明、枚举、只读属性 |
| 框架 | Laravel 11/12 | 全栈 MVC |
| 前端 | Blade + Tailwind CSS + Alpine.js | 快速出页面，无前后端分离 |
| 数据库 | MySQL 8 / MariaDB | 主数据存储 |
| 缓存/队列 | Redis | 会话、队列、在线状态 |
| 文件 | MinIO（或本地存储） | Laravel Filesystem 统一接口 |
| 搜索 | Meilisearch + Laravel Scout | 中文搜索 |
| 实时 | Laravel Reverb | WebSocket 聊天室 |
| 认证 | Laravel Sanctum | API Token + SPA 认证 |
| 权限 | spatie/laravel-permission | 角色权限 |
| Markdown | league/commonmark + HTML Purifier | 渲染 + XSS 过滤 |
| 测试 | Pest / PHPUnit | 单元与功能测试 |
| 静态分析 | PHPStan / Psalm | 提前发现逻辑问题 |
| 安全扫描 | Semgrep + Composer audit | 代码审计 + 依赖漏洞 |
| 部署 | Docker Compose | 一键启动 |

---

## 3. 项目目录结构

```text
noircat-php/
├── app/
│   ├── Http/Controllers/
│   ├── Models/
│   ├── Services/
│   ├── Policies/
│   ├── Plugins/              # 公会插件系统
│   └── Providers/
├── database/migrations/
├── resources/views/
├── routes/
│   ├── web.php
│   └── api.php
├── public/
├── tests/
├── docker-compose.yml
├── PROJECT_SPEC_PHP.md
└── AGENTS.md
```

---

## 4. 数据库核心表

以下为 Laravel 迁移摘要。

```php
// users 表
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('username')->unique();
    $table->string('email')->unique();
    $table->string('password');
    $table->string('avatar')->nullable();
    $table->string('role')->default('user'); // user/guild_admin/moderator/admin
    $table->timestamps();
});

// books 表
Schema::create('books', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->string('author')->nullable();
    $table->text('description')->nullable();
    $table->string('cover_url')->nullable();
    $table->string('format');       // epub/pdf/mobi/txt
    $table->string('file_url');
    $table->bigInteger('file_size')->nullable();
    $table->foreignId('category_id')->nullable();
    $table->foreignId('uploader_id');
    $table->integer('download_count')->default(0);
    $table->decimal('rating', 3, 1)->default(0);
    $table->timestamps();
});

// posts 表
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->text('content');         // Markdown 原始内容
    $table->text('content_html')->nullable();
    $table->foreignId('author_id');
    $table->foreignId('category_id')->nullable();
    $table->string('status')->default('published'); // draft/published/deleted
    $table->integer('view_count')->default(0);
    $table->integer('like_count')->default(0);
    $table->integer('comment_count')->default(0);
    $table->timestamps();
});

// comments 表
Schema::create('comments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('post_id');
    $table->foreignId('parent_id')->nullable();
    $table->foreignId('author_id');
    $table->text('content');
    $table->integer('like_count')->default(0);
    $table->timestamps();
});

// guilds 表
Schema::create('guilds', function (Blueprint $table) {
    $table->id();
    $table->string('name')->unique();
    $table->text('description')->nullable();
    $table->string('avatar_url')->nullable();
    $table->foreignId('owner_id');
    $table->integer('level')->default(1);
    $table->integer('max_members')->default(50);
    $table->integer('points')->default(0);
    $table->timestamps();
});

// guild_members 表
Schema::create('guild_members', function (Blueprint $table) {
    $table->foreignId('guild_id');
    $table->foreignId('user_id');
    $table->string('role')->default('member'); // owner/vice_leader/elder/member
    $table->integer('contribution')->default(0);
    $table->timestamp('joined_at')->useCurrent();
    $table->primary(['guild_id', 'user_id']);
});

// guild_plugins 表
Schema::create('guild_plugins', function (Blueprint $table) {
    $table->id();
    $table->foreignId('guild_id');
    $table->string('plugin_id');
    $table->string('version')->nullable();
    $table->json('config')->nullable();
    $table->boolean('enabled')->default(true);
    $table->foreignId('installed_by');
    $table->timestamp('installed_at')->useCurrent();
});

// guild_messages 表
Schema::create('guild_messages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('guild_id');
    $table->foreignId('sender_id');
    $table->text('content');
    $table->string('message_type')->default('text'); // text/image/system
    $table->timestamps();
});

// learning_paths 表
Schema::create('learning_paths', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->text('description')->nullable();
    $table->string('category');      // network_security/web_dev 等
    $table->string('difficulty');    // beginner/intermediate/advanced
    $table->string('cover_url')->nullable();
    $table->timestamps();
});

// learning_items 表
Schema::create('learning_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('stage_id');
    $table->string('title');
    $table->text('content')->nullable(); // Markdown
    $table->string('item_type');         // article/video/exercise/quiz
    $table->string('resource_url')->nullable();
    $table->integer('sort_order');
    $table->timestamps();
});

// user_progress 表
Schema::create('user_progress', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id');
    $table->foreignId('item_id');
    $table->string('status')->default('not_started'); // not_started/in_progress/completed
    $table->integer('score')->nullable();
    $table->timestamp('started_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->unique(['user_id', 'item_id']);
    $table->timestamps();
});
```

---

## 5. API 设计规范

- 资源命名：名词复数，如 `/api/books`、`/api/posts`
- HTTP 方法：GET 查询 / POST 创建 / PUT 更新 / DELETE 删除
- 认证：JWT Token 放在 `Authorization: Bearer <token>` 头
- 分页：`?page=1&limit=20`，响应含 `total`、`page`、`limit`
- 错误格式：`{ "code": 400, "message": "...", "data": null }`

### 核心接口清单

```text
# 电子书
GET    /api/books              # 搜索/列表
GET    /api/books/{id}         # 详情
POST   /api/books              # 上传
GET    /api/books/{id}/download # 下载
GET    /api/books/{id}/read     # 在线阅读

# 论坛
GET    /api/posts              # 帖子列表
POST   /api/posts              # 发帖
POST   /api/posts/{id}/comments # 评论
GET    /api/search?q=关键词     # 全文搜索

# 公会
POST   /api/guilds             # 创建公会
GET    /api/guilds/{id}        # 公会详情
POST   /api/guilds/{id}/join   # 申请加入
GET    /api/guilds/{id}/plugins # 已安装插件

# 学习
GET    /api/learn/paths        # 路线列表
GET    /api/learn/paths/{id}   # 路线详情
POST   /api/learn/items/{id}/complete  # 标记完成
GET    /api/learn/my-progress  # 我的进度
```

---

## 6. 公会插件系统

### 插件 Manifest 格式

```json
{
  "id": "daily-checkin",
  "name": "每日签到",
  "version": "1.0.0",
  "description": "公会成员每日签到获得积分",
  "entry": "src/Plugin.php",
  "permissions": ["guild:member:read", "guild:points:write"],
  "hooks": ["onMemberJoin", "onDailyReset"],
  "config": {
    "pointsPerCheckin": { "type": "number", "default": 10 }
  }
}
```

### 插件入口结构（Laravel 服务提供者风格）

```php
// app/Plugins/DailyCheckin/Plugin.php
namespace App\Plugins\DailyCheckin;

use App\Services\PluginContext;

class Plugin
{
    public function activate(PluginContext $ctx): void
    {
        // 注册路由
        $ctx->router->post('/checkin', function ($request) use ($ctx) {
            $userId = $request->user()->id;
            $today = now()->toDateString();

            $exists = $ctx->db->table('checkins')
                ->where('guild_id', $ctx->guildId)
                ->where('user_id', $userId)
                ->where('date', $today)
                ->exists();

            if ($exists) {
                return response()->json(['success' => false, 'message' => '今天已经签到过了']);
            }

            $ctx->db->table('checkins')->insert([
                'guild_id' => $ctx->guildId,
                'user_id' => $userId,
                'date' => $today,
            ]);

            $ctx->services->guildPoints->add($ctx->guildId, $userId, 10);
            $ctx->emit('member.checkin', ['guildId' => $ctx->guildId, 'userId' => $userId]);

            return response()->json(['success' => true, 'message' => '签到成功！+10积分']);
        });

        // 注册定时任务
        $ctx->schedule->daily('0 0 * * *', function () use ($ctx) {
            $ctx->db->table('checkins')->where('guild_id', $ctx->guildId)->delete();
        });

        // 监听事件
        $ctx->on('member.checkin', function ($data) use ($ctx) {
            // 可触发成就检查
        });
    }

    public function deactivate(PluginContext $ctx): void
    {
        $ctx->cleanup();
    }
}
```

### 插件通信机制

- **事件总线**：Laravel Event + Listener，通过 `$ctx->emit()` / `$ctx->on()` 封装
- **服务注册表**：Laravel Service Container，插件可注册服务如 `guildPoints`
- **沙箱限制**：插件只能通过 `$ctx->db` 操作数据，不能直接使用 `DB::` 或文件系统

---

## 7. 安全审计要求

为提升代码审计能力，项目采用双分支策略：

- `main`：安全版，正常修复漏洞
- `vuln-lab`：漏洞版，故意保留经典漏洞，用于练习渗透

每个模块完成后，需在 `vuln-lab` 分支制造对应漏洞并写审计报告。

### 审计清单示例（论坛发帖）

```markdown
## 发帖功能审计清单
- [ ] 输入是否过滤？Markdown 渲染后是否经 HTML Purifier？
- [ ] SQL 是否用 Eloquent/Query Builder 参数绑定？
- [ ] 是否校验 CSRF Token？
- [ ] 是否校验用户权限（能否删别人帖子）？
- [ ] 是否限制发帖频率？
- [ ] 是否存在 SSTI、XXE、SSRF？
```

### 工具链

| 工具 | 用途 |
|------|------|
| PHPStan / Psalm | 静态分析，发现类型和逻辑问题 |
| Semgrep | 自定义规则扫描危险函数 |
| RIPS / Fortify | PHP 代码审计 |
| Composer audit | 依赖漏洞扫描 |
| Burp Suite / OWASP ZAP | 动态测试 |
| DVWA / Pikachu / BWAPP | 经典 PHP 漏洞靶场 |

### 审计报告

每完成一个模块，写一份 `docs/audit/{module}-audit.md`，记录：
- 发现的问题
- 漏洞原理
- 修复方案
- 验证过程

---

## 8. 开发约定

### 代码风格
- PHP 8.3+，使用类型声明和枚举
- 遵循 PSR-12
- 控制器瘦，服务层厚
- 所有 SQL 用 Eloquent 或 Query Builder 参数绑定

### Git 提交规范
```text
feat: 新功能
fix: 修复
docs: 文档
refactor: 重构
chore: 杂项
```

### 测试要求
- Pest / PHPUnit 覆盖核心 API
- 每个模块至少 3 个测试用例

---

## 9. 开发优先级

```text
Phase 1: Laravel 脚手架 + 用户认证
Phase 2: 论坛模块（发帖/回帖/Markdown 渲染）
Phase 3: 电子书模块（上传/检索/在线阅读）
Phase 4: 学习成长模块（路线/进度/测验）
Phase 5: 公会系统（成员/聊天室/插件框架）
```

---

## 10. 安全要求

- 所有 API 需 Sanctum 认证（公共接口除外）
- 文件上传验证类型和大小，禁止可执行文件
- Markdown 渲染后经 HTML Purifier 过滤
- 使用 Eloquent 防止 SQL 注入
- 插件运行在沙箱中，仅通过受控 API 操作
- `.env` 不入库，依赖用 Composer audit 扫描

---

## 11. 开发路线图（人看的版本）

### Phase 0：环境准备（3–5 天）

**目标**：Laravel 项目能跑，能推 GitHub。

- [ ] 安装 Laragon（PHP 8.3 + MySQL）
- [ ] 创建 Laravel 项目
- [ ] 关联 GitHub 仓库 `everret-chen/NoirCat-php`
- [ ] 创建 `main`、`dev`、`vuln-lab` 分支
- [ ] 配置 `.env`，创建数据库 `noircat`
- [ ] `php artisan migrate` 成功
- [ ] `php artisan serve` 能访问首页

**验收**：浏览器打开 `http://127.0.0.1:8000`，显示 Laravel 欢迎页。

---

### Phase 1：用户认证 + 首页（1–2 周）

**目标**：能注册、登录、看到 NoirCat 首页。

- [ ] 安装 Sanctum、spatie/laravel-permission
- [ ] 创建 `users` 表
- [ ] 注册页、登录页、登出
- [ ] 全局布局（导航栏 + 页脚）
- [ ] 首页（四大模块卡片、最新帖子、推荐路线）
- [ ] 写 3 个认证测试
- [ ] **安全审计**：弱密码、Session 固定、CSRF

**验收**：首页是 NoirCat 风格，右上角能注册登录。

---

### Phase 2：论坛模块（2–3 周）

**目标**：能发帖、回帖、搜索。

- [ ] `posts`、`comments`、`tags`、`post_tags` 表
- [ ] 帖子列表、详情、发帖、编辑
- [ ] Markdown 渲染 + HTML Purifier
- [ ] 评论、点赞
- [ ] Meilisearch 全文搜索
- [ ] 写测试
- [ ] **安全审计**：SQL 注入、XSS、CSRF、越权删帖

**验收**：论坛能发帖，代码块有高亮，搜索能出结果。

---

### Phase 3：电子书模块（2–3 周）

**目标**：能上传、下载、在线阅读。

- [ ] `books`、`categories`、`download_records`、`book_reviews` 表
- [ ] 文件上传到 MinIO
- [ ] 书籍列表、详情、上传页
- [ ] EPUB.js / PDF.js 在线阅读
- [ ] 评分、下载计数
- [ ] 写测试
- [ ] **安全审计**：文件上传绕过、路径穿越、越权下载

**验收**：能上传一本 EPUB，在浏览器里翻页阅读。

---

### Phase 4：学习成长模块（3–4 周）

**目标**：能看学习路线，记录进度。

- [ ] `learning_paths`、`learning_stages`、`learning_items`、`user_progress` 表
- [ ] 路线列表、详情、知识点页
- [ ] 标记完成、测验评分
- [ ] 个人学习仪表盘
- [ ] 成就徽章（可选）
- [ ] 写测试
- [ ] **安全审计**：IDOR、进度篡改

**验收**：打开“Web 安全入门”路线，点开知识点，进度条会涨。

---

### Phase 5：公会系统（4–6 周，最难）

**目标**：能建公会、聊天、装插件。

- [ ] `guilds`、`guild_members`、`guild_plugins`、`guild_messages` 表
- [ ] 公会创建、成员管理
- [ ] Laravel Reverb 聊天室
- [ ] 插件框架：manifest 解析、沙箱、事件总线
- [ ] 示例插件：每日签到
- [ ] 写测试
- [ ] **安全审计**：插件沙箱逃逸、WebSocket 伪造、越权

**验收**：建一个公会，在聊天室说话，装个签到插件点一下加 10 分。

---

### Phase 6：部署上线（1–2 周）

**目标**：网站能在公网访问。

- [ ] Docker Compose 生产配置
- [ ] Nginx + PHP-FPM + SSL
- [ ] 备份脚本（数据库 + 文件）
- [ ] Uptime Kuma 监控
- [ ] 安全加固（防火墙、Fail2ban）

**验收**：朋友用手机能打开你的网站。

---

## 12. DSH 提示词汇总

> 使用方式：每条提示词复制到 DSH 对话框，按“模式选择”切换对应模式后发送。  
> 项目根目录需已放置 `PROJECT_SPEC_PHP.md` 和 `AGENTS.md`。

### 提示词 1：初始化 Laravel 项目（Phase 1）

**DSH 模式选择：标准模式**

```text
读取 PROJECT_SPEC_PHP.md。

当前任务：创建 Laravel 项目脚手架。

要求：
1. 按 PROJECT_SPEC_PHP.md 第2节的目录结构，初始化 Laravel 11/12 项目
2. 配置 MySQL、Redis、MinIO（docker-compose 已提供）
3. 安装 Sanctum、spatie/laravel-permission、league/commonmark、HTML Purifier、Laravel Scout
4. 创建 users 迁移和 User 模型
5. 实现注册/登录 API（Sanctum）
6. 确保 `php artisan serve` 能启动

只做以上内容，不要扩展。
```

### 提示词 2：开发论坛模块（Phase 2）

**DSH 模式选择：标准模式**

```text
读取 PROJECT_SPEC_PHP.md。

当前任务：实现论坛模块。

要求：
1. 创建 Post、Comment、Tag、PostTag 迁移和模型
2. 实现帖子 CRUD API（见第4节接口清单）
3. Markdown 渲染用 league/commonmark + HTML Purifier，渲染后过滤 XSS
4. 实现帖子列表页、详情页、发帖/编辑页（Blade + Tailwind + Alpine.js）
5. 所有 SQL 用 Eloquent，禁止拼接

只做以上内容。测试用 Pest 验证 API 可用。
```

### 提示词 3：开发电子书模块（Phase 3）

**DSH 模式选择：标准模式**

```text
读取 PROJECT_SPEC_PHP.md。

当前任务：实现电子书模块。

要求：
1. 创建 Book 迁移和模型
2. 实现 Book 的 CRUD + 文件上传/下载 API
3. 文件存储接入 MinIO（Laravel Filesystem）
4. 实现书籍列表页、详情页、在线阅读页（EPUB 用 epub.js，PDF 用 pdf.js）、上传页
5. 上传时限制文件类型（epub/pdf/mobi/txt）和大小（50MB）

只做以上内容。
```

### 提示词 4：开发学习成长模块（Phase 4）

**DSH 模式选择：标准模式**

```text
读取 PROJECT_SPEC_PHP.md。

当前任务：实现学习成长模块。

要求：
1. 创建 LearningPath、LearningStage、LearningItem、UserProgress 迁移和模型
2. 实现路线 CRUD、获取路线详情（树形结构）、标记知识点完成、提交测验、获取用户进度统计
3. 实现路线列表页、路线详情页、知识点详情页、个人学习仪表盘（Blade + Tailwind）
4. 所有 SQL 用 Eloquent

只做以上内容。
```

### 提示词 5：开发公会系统（Phase 5）—— 插件框架

**DSH 模式选择：PTC 模式**

```text
读取 PROJECT_SPEC_PHP.md。

当前任务：实现公会系统基础 + 插件框架。

要求：
1. 创建 Guild、GuildMember、GuildPlugin、GuildMessage 迁移和模型
2. 实现公会 CRUD、成员管理（邀请/踢出/角色分配）
3. 实现插件管理 API（安装/卸载/启用/禁用/更新配置）
4. 实现插件运行时：加载 manifest，沙箱执行 activate/deactivate
5. 实现事件总线：$ctx->emit / $ctx->on（基于 Laravel Event）
6. 实现服务注册表：$ctx->services（基于 Laravel Service Container）
7. WebSocket 网关（Laravel Reverb）：公会聊天室
8. 创建示例插件：每日签到（见第5节 Manifest 格式）
9. 实现公会列表/详情页、成员管理页、聊天室、插件管理页（Blade + Tailwind + Alpine.js）

只做以上内容。
```

### 提示词 6：日常小任务（通用模板）

**DSH 模式选择：极简模式**

```text
读取 PROJECT_SPEC_PHP.md。

任务：[一句话描述，如“给 Post 模型加一个 isPinned 字段”]

约束：不改动其他模块，完成后运行测试。
```

### 提示词 7：修复 Bug（通用模板）

**DSH 模式选择：PTC 模式**

```text
读取 PROJECT_SPEC_PHP.md。

Bug 描述：[粘贴错误信息或复现步骤]

请定位并修复。要求：
1. 先用 grep/ripgrep 搜索相关代码
2. 读取相关文件
3. 定位根因
4. 修复
5. 运行相关测试验证

不要重构无关代码。
```

### 提示词 8：批量重构

**DSH 模式选择：PTC 模式**

```text
读取 PROJECT_SPEC_PHP.md。

任务：批量重构。

要求：
1. 搜索所有 [模式，如 “dd(”]
2. 替换为 [目标，如 “Log::debug()”]
3. 统计修改文件数
4. 运行 PHPStan 检查

范围：[指定目录]
```

### 提示词 9：安全审计专用

**DSH 模式选择：标准模式**

```text
读取 PROJECT_SPEC_PHP.md。

当前任务：对 [模块名] 进行安全审计。

要求：
1. 检查所有输入是否过滤，输出是否转义
2. 检查 SQL 是否全部使用 Eloquent 参数绑定
3. 检查 CSRF、XSS、文件上传、越权、SSRF、XXE
4. 使用 Semgrep 和 PHPStan 扫描
5. 输出审计报告到 docs/audit/[模块名]-audit.md

只做审计，不要修改功能代码。
```

---

## 13. AGENTS.md 模板

```markdown
# NoirCat PHP 项目指令

## 项目
墨猫问雪 / NoirCat，中英双语网络安全学习社区。
技术栈见 PROJECT_SPEC_PHP.md。

## 代码约定
- PHP 8.3+，PSR-12，2 空格缩进
- 控制器瘦，服务层厚
- 所有 SQL 用 Eloquent 或 Query Builder 参数绑定
- API 响应格式：{ code, message, data }

## 禁止
- 不改动 database/migrations/ 下的历史文件
- 不直接操作文件系统，用 Laravel Filesystem
- 不提交 .env、vendor、node_modules

## 测试
- 后端：php artisan test
- 静态分析：vendor/bin/phpstan analyse

## 沟通
- 回复用中文
- 代码注释用英文
- 每完成一个模块，更新 docs/phases.md
```

---

## 14. 常用命令速查

```bash
# 启动开发服务器
php artisan serve

# 创建数据库迁移
php artisan make:migration create_posts_table

# 执行迁移
php artisan migrate

# 回滚迁移
php artisan migrate:rollback

# 创建控制器
php artisan make:controller PostController

# 创建模型
php artisan make:model Post -m

# 运行测试
php artisan test

# 静态分析
vendor/bin/phpstan analyse

# 依赖漏洞扫描
composer audit
```

---

## 15. 学习资源

| 资源 | 链接 |
|------|------|
| Laravel 官方文档 | https://laravel.com/docs |
| Blade 模板 | https://laravel.com/docs/blade |
| Tailwind CSS | https://tailwindcss.com/docs |
| Alpine.js | https://alpinejs.dev/start-here |
| OWASP Top 10 | https://owasp.org/www-project-top-ten/ |
| PHP 安全手册 | https://www.php.net/manual/en/security.php |

---

## 16. 三条铁律

1. **不跳阶段**：论坛没跑通，不要碰电子书。
2. **每个模块必须审计**：开发完不是结束，制造漏洞并写报告才算完成。
3. **每天提交 Git**：哪怕只改一行，也要提交，保持记录。

---

## 17. 当前进度

**正在执行：Phase 0**

- [x] 创建 GitHub 仓库 `everret-chen/NoirCat-php`
- [x] 创建分支 `main`、`dev`、`vuln-lab`
- [ ] 安装 Laragon（PHP 8.3 + MySQL）
- [ ] 创建 Laravel 项目
- [ ] 配置 `.env`，创建数据库
- [ ] 首次提交推送

**下一步任务**：安装 Laragon → 验证 `php -v` 和 `composer -V`。

---

> **以问为刃，以雪为盟。**  
> Ask the snow, secure the code.
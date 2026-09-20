# 墨猫问雪 / NoirCat — PHP 版项目开发方案书

## 0. 项目概览

- 项目名：墨猫问雪 / NoirCat
- 定位：中英双语网络安全学习社区
- 核心模块：电子书资源 / 博客论坛 / 公会系统 / 学习成长
- 设计理念：核心精简，功能插件化
- 开发目标：巩固 PHP，同时提升代码审计与安全开发能力

## 1. 技术栈

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

## 2. 项目目录结构

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

## 3. 数据库核心表（Laravel 迁移摘要）

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

## 4. API 设计规范

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

## 5. 公会插件系统（Laravel 实现）

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

## 6. 安全审计要求（重点）

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

## 7. 开发约定

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

## 8. 开发优先级

```text
Phase 1: Laravel 脚手架 + 用户认证
Phase 2: 论坛模块（发帖/回帖/Markdown 渲染）
Phase 3: 电子书模块（上传/检索/在线阅读）
Phase 4: 学习成长模块（路线/进度/测验）
Phase 5: 公会系统（成员/聊天室/插件框架）
```

## 9. 安全要求

- 所有 API 需 Sanctum 认证（公共接口除外）
- 文件上传验证类型和大小，禁止可执行文件
- Markdown 渲染后经 HTML Purifier 过滤
- 使用 Eloquent 防止 SQL 注入
- 插件运行在沙箱中，仅通过受控 API 操作
- `.env` 不入库，依赖用 Composer audit 扫描
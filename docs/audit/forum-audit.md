# 论坛模块审计报告 / Forum module audit

- **范围**：`app/Services/{PostService,CommentService,MarkdownService}.php`、`app/Policies/{PostPolicy,CommentPolicy}.php`、
  `app/Http/Controllers/Api/{PostController,CommentController,CategoryController,SearchController}.php`、
  `app/Http/Controllers/Web/ForumController.php`、`app/Http/Requests/Forum/*`、`app/Http/Resources/{PostResource,CommentResource}.php`、
  `routes/{api,web}.php`（论坛部分）
- **版本**：`dev` 分支 Phase 2
- **对照漏洞版**：`vuln-lab` 分支同名模块，见 `forum-vuln-lab.md`

## 1. 审计清单与结论

| 检查项 | 结论 | 落点 |
|---|---|---|
| 作者伪造（Mass Assignment） | ✅ 请求不接收 `author_id`，服务端始终取会话用户；`Post::$fillable` 不含 `author_id`/`content_html` | `PostService::create` / `StorePostRequest` |
| `content_html` 被客户端注入 | ✅ 不接受客户端 HTML，服务端根据 Markdown 原文重新渲染 | 同上 |
| 草稿越权读取（IDOR） | ✅ `PostPolicy::view`：草稿仅作者与有 `post:delete_any` 者可见；公开列表**永远**只查已发布 | `PostPolicy` / `PostService::paginate` |
| 草稿出现在列表/搜索 | ✅ `include_unpublished` 只能由 Web 控制器按会话注入，客户端参数无法打开；`/api/search` 同样只查已发布 | `ForumController::index` / `SearchController` |
| 越权改帖 / 删帖 | ✅ `PostPolicy::update/delete`：本人（需 `post:update_own`/`post:delete_own`）或版主（`post:delete_any`），均有测试 | `PostPolicy` |
| 置顶越权 | ✅ `pin` 需 `post:pin` 权限，普通用户 403/2001 | `PostPolicy::pin` |
| **草稿可被评论 / 草稿评论可被读** | ⚠️ **本轮发现并修复**：新增 `PostPolicy::comment`（仅已发布帖可评论），并在 API/Web 两个入口对评论列表补 `authorize('view', $post)` | 见第 2 节 #1 |
| 回复挂到别帖评论 | ✅ 校验父评论存在且 `post_id` 与目标帖一致，否则 422/3001 | `CommentService::create` |
| 评论嵌套深度绕过 | ✅ 深度上限 3 层，超限 422/4001；循环带硬上限，恶意自引用不会死循环 | `CommentService::depthOf` |
| **评论计数漂移** | ⚠️ **本轮发现并修复**：删除/隐藏评论后 `comment_count` 不再虚高 | 见第 2 节 #2 |
| 重复点赞叠加计数 | ✅ `likes` 唯一索引（user+target）+ `firstOrCreate`，计数只在首次创建时自增 | `PostService::like` |
| 浏览量刷量 | ✅ `Cache::add` 每访客每帖每小时只计一次 | `PostService::recordView` |
| 存储型 XSS（帖子正文） | ✅ 双层过滤：CommonMark `html_input=strip` + `allow_unsafe_links=false`，再经 HTMLPurifier 白名单；`script`/`onerror`/`javascript:` 全部剥离 | `MarkdownService` |
| 外链反向标签劫持 | ✅ HTMLPurifier `HTML.TargetBlank` + 默认 `TargetNoopener/Noreferrer`：输出为 `rel="noreferrer noopener"` | 同上（实测） |
| 存储型 XSS（评论） | ✅ 评论按纯文本存储，输出走 Blade 转义，不存在 HTML 通路 | `CommentService` / `comment` 视图 |
| **搜索通配符** | ⚠️ **本轮发现并修复**：SQLite 下 `%`/`_`/`\` 转义失效导致搜不到 | 见第 2 节 #3 |
| 搜索 SQL 注入 | ✅ 参数绑定 + 显式 `ESCAPE '!'`，无字符串拼接 | `PostService::applySearch` |
| 列表分页上限 | ✅ `limit` 1–100（搜索 1–50），不能拉全表 | `PostController::index` / `SearchController` |
| 写接口限流 | ✅ `throttle:posts` 10 次/分钟；搜索 `throttle:search` 60 次/分钟 | `config/noircat.php` |
| 未验证邮箱能否发帖 | ✅ 写接口全部挂 `verified`：API 返回 `403/1005`，Web 跳转个人页并提示 | `routes/api.php` / `routes/web.php` |
| 审计留痕 | ✅ 发帖/改帖/删帖/置顶/点赞/评论创建/隐藏/删除全部写 `audit_logs` | `PostService` / `CommentService` |
| 软删除一致性 | ✅ `posts`/`comments` 软删除，列表与详情默认排除 | 模型 `SoftDeletes` |

## 2. 发现并修复的问题

| # | 问题 | 风险 | 处理 |
|---|---|---|---|
| 1 | `POST /api/posts/{post}/comments`、Web `POST /forum/{post}/comments` 只校验"能评论"权限，不校验"能看这个帖"；`GET /api/posts/{post}/comments` 同样没有 `view` 授权 | 任何登录用户只要猜 ID（自增主键）就能给他人**草稿**灌评论，并能读到草稿下的评论串 | 新增 `PostPolicy::comment`（仅已发布帖可评论），评论列表补 `authorize('view', $post)`；API 与 Web 双入口同步；新增 3 个用例（草稿评论 403、草稿评论串 403 且作者仍可读、Web 端草稿评论 403） |
| 2 | 评论**删除/隐藏**后 `post.comment_count` 不减，只有创建时 `increment` | 计数虚高：列表页与详情页显示的评论数与实际可见评论不符 | `CommentService::delete/hide` 在事务内判断"原状态是否可见"，是则 `decrement`（带 `> 0` 下限保护）；隐藏两次不会重复减；新增 1 个用例 |
| 3 | LIKE 通配符转义用裸反斜杠（`\%` / `\_`），但 SQLite 的 LIKE **没有默认转义字符** | MySQL 下正常，SQLite 下含 `%`/`_`/`\` 的关键词**一条都搜不到**（实测 `"100%"`、`"a_b"`、`"C:\path"` 命中数均为 0） | 改为显式 `LIKE ? ESCAPE '!'` 并转义 `!`、`%`、`_`；两个数据库语义一致。实测同三条关键词命中数恢复为 1；新增用例同时断言 `%%` 仍不匹配全表 |
| 4 | `posts.content_html` 未在 `$fillable`，赋值为静默丢弃 | 详情页正文渲染为空（数据在但展示不出） | 改为显式赋值 + 开启 `Model::preventSilentlyDiscardingAttributes(! isProduction())`，让此类问题直接报错而不是静默 |
| 5 | `PostService::paginate()` 返回 Builder 而未调用 `->paginate()` | 所有列表接口 500 | 修正返回值类型为 `LengthAwarePaginator`，PHPStan level 6 可拦截同类问题 |
| 6 | 公开 API 路由用默认 `web` guard 解析 `$request->user()` | 带令牌访问公开路由时策略把作者当游客，草稿/点赞判权错误 | 新增 `UseSanctumGuard` 中间件（`Auth::shouldUse('sanctum')`）前置到 api 分组 |
| 7 | `verification.verify` 路由名被 API 路由占用 | 邮件里的验证链接点开是一片 JSON 信封，普通用户看不懂 | 路由名交给 Blade 路由（`GET /email/verify/{id}/{hash}` → 302 到个人页并提示），API 保留 `api.auth.verification.verify` 供程序化调用 |
| 8 | `verified` 中间件已实现但**从未挂到任何路由** | 审计文档声称"未验证邮箱访问受保护接口会被拦截"，实际未生效，注册即可发帖 | 论坛写接口（发帖/改帖/评论/点赞）全部挂 `verified`；Web 端未验证访问跳个人页（那里有重发按钮），API 端返回 `403/1005`；删除与置顶不设门槛，避免版主邮箱异常时无法处置内容 |
| 9 | PHPStan level 6 报 `nullsafe.neverNull`、闭包返回值多余类型 | 静态分析红 | `Web\ForumController` 改为局部变量 + 非空判断，闭包返回类型收紧为 `int` |

## 3. 验证过程

### 自动化测试

```text
php artisan test
  Tests:    115 passed (463 assertions)
vendor/bin/phpstan analyse   (larastan, level 6)
  [OK] No errors
```

论坛相关用例分布：`PostTest` 11 例、`CommentTest` 10 例、`SearchTest` 5 例、`WebPagesTest` 20 例、`MarkdownServiceTest` 6 例。

### 真实 HTTP 冒烟（`php -S localhost:8000`，浏览器式 Cookie + CSRF）

```text
guest        /、/forum、/login、/register 200；/forum/create 302→/login；不存在路径 404
registration 表单注册成功并登录；无 _token 的 POST 返回 419        ← CSRF 实测
verified gate 未验证：/forum/create 与发帖均 302→/profile，库中无帖子；重发按钮投递队列
              邮件签名链接 302→页面（不再是 JSON）；随后发帖放行
forum write  发帖/改帖成功；<script>、javascript:、onerror 全部被剥离；<strong>、<code> 正常渲染
comments     & 评论写入并在页面转义显示；点赞/取消点赞生效
account      /profile、/sessions 200；资料更新成功
i18n         ?lang=en 切换英文；非法 lang 值回退不报错
session      登出后受保护页 302→/login；错误密码不泄露原因；重新登录成功
PASS 52  FAIL 0
```

### 复现原始缺陷（修复前）

```text
search "100%"   -> 0 hit(s)     search "a_b" -> 0 hit(s)     search "C:\path" -> 0 hit(s)
修复后：
search "100%"   -> 1 hit(s)     search "a_b" -> 1 hit(s)     search "C:\path" -> 1 hit(s)
```

## 4. 已知残留风险

1. **Markdown 允许外链图片**（`img[src]` 在白名单内）：读者打开帖子会把 IP / Referer 暴露给图床域名。
   若要彻底消除，需要图片代理或域名白名单，属于 Phase 3 之后的内容治理项。
2. **LIKE 检索的能力上限**：只做标题/正文字面子串匹配，无分词与相关度排序，数据量大后性能与效果都会退化。
   Phase 4 计划用 Meilisearch + Scout 替换（接口形状不变，只换 `PostService::paginate` 的搜索实现）。
3. **`comment_count` 语义 = 可见评论数**：隐藏评论会使其减一。若后台需要"历史累计评论数"，应另加列，而不是改回虚高。
4. **草稿评论被禁止**：作者本人也不能给自己的草稿留言（草稿是未发布状态，评论线程对其无意义）。若以后需要"自留备注"，应使用草稿备注字段而不是评论系统。

## 5. 复盘

1. **策略方法要有对应的入口调用**：`PostPolicy` 里少一个 `comment` 方法，两个入口就都漏了；"服务层厚"不能替代控制器上的 `authorize`。
2. **计数列是状态，不是日志**：任何改变可见性的操作（隐藏、删除）都必须同步计数，否则前端展示会长期错误。
3. **跨数据库的 SQL 小语义必须实测**：`LIKE` 的默认转义字符 MySQL 有、SQLite 没有，只靠"看起来对"的转义代码会静默失配。
4. **文档声称的缓解措施必须有路由/测试兜底**：`verified` 中间件写好了却没接线，审计表会给出错误的安全感。此后每条"✅"都要能指向测试或路由。

## 6. 治理闭环（Phase 2.5 追加）

举报与版主处置是论坛的第二条主线：**成员报告问题，版主决定处置**，两者在数据与审计上完全分离。

| 检查项 | 结论 | 落点 |
|---|---|---|
| 举报能否刷屏 | ✅ `reports` 唯一索引 `(reporter_id, reportable_type, reportable_id)` + 服务层 409；同一人对同一对象只能举报一次 | `ReportService::create` / 迁移 |
| 举报对象是否受限 | ✅ 只接受 `Post` / `Comment`（白名单），未知类型 422；举报已删除内容 404 | `ReportService::REPORTABLE` |
| 能否举报自己的内容 | ✅ 422/4001，减少噪音与"自我表演式举报" | `ReportService::create` |
| 举报正文注入 | ✅ 只存纯文本 `detail`（≤1000），前端转义输出 | `StoreReportRequest` |
| 举报人是否可信 | ✅ `reporter_id` 永远取自会话，请求体不接受 | `Report::create` + 控制器 |
| 处置权限 | ✅ `ReportPolicy`：`report:create` 人人有，`report:handle` 只有版主/管理员；普通成员 403 | `ReportPolicy` |
| 重复处置 | ✅ 已关闭的举报再处置返回 409，不会覆盖"谁决定了什么" | `ReportService::close` |
| 处置留痕 | ✅ `handled_by`/`handled_at`/`resolution_note` + `audit_logs` 的 `moderation.report.resolved|dismissed` | `ReportService::close` |
| 内容被删除后的举报 | ✅ 队列里保留为"内容已不存在"（`content_available=false`），版主仍可结案，不产生无法关闭的孤儿条目 | `ReportResource` / 治理台视图 |
| 加精 / 锁定 / 移版 越权 | ✅ 三个独立权限点 `post:feature` / `post:lock` / `post:move`，普通成员 403；每次操作写审计 | `PostPolicy` / `ModerationService` |
| 锁定帖还能不能回帖 | ✅ 策略 + 服务双保险（`PostPolicy::comment` 与 `CommentService::create` 都检查），前端隐藏评论框并提示 | 同上 |
| 回收站越权 | ✅ 版主见全部、成员只见自己的（过滤在服务层按权限施加，不信任请求参数）；恢复按"删除权"判定 | `ModerationService::trashPosts/trashComments` / `PostPolicy::restore` |
| 恢复软删除内容 | ✅ `onlyTrashed()` 查回 + 恢复写审计；恢复评论会同步把 `comment_count` 加回 | `ModerationService::restorePost` / `CommentService::restore` |
| 评论隐藏/取消隐藏 | ✅ 隐藏减计数、取消隐藏加计数，重复操作返回 409 而不是静默成功 | `CommentService::hide/unhide` |
| 治理页面上的业务异常 | ⚠️ **本轮发现并修复**：Web 页面上的"业务规则冲突"原本会渲染成 500 | 见第 7 节 #1 |
| 治理页面越权访问 | ✅ `/moderation/reports` 403；导航入口只对 `report:handle` 显示（含待处理计数） | `ModerationController` / 布局 |

## 7. 治理环节发现并修复的问题

| # | 问题 | 风险 | 处理 |
|---|---|---|---|
| 1 | 业务规则冲突（例如"该举报已被处理"）在 Web 页面上抛 `BusinessException`，而异常渲染器只处理 API → 框架按未知异常处理，**返回 500 错误页** | 管理员重复点击看到的是"服务器错误"，且 500 会污染日志与监控 | `bootstrap/app.php` 增加 Web 分支：`back()->with('error', message)`；API 仍走信封。测试断言 302 + 会话错误消息 |
| 2 | 报表页把"隐藏/删除评论"的能力只放在 API | 版主在页面上无法治理评论，"管理员账号"形同虚设 | 评论行内加隐藏/取消隐藏/删除/恢复表单，全部走策略授权 |
| 3 | 列表页与详情页看不到加精/锁定状态 | 版主处置后没有任何视觉反馈 | 新增 `badge-featured` / `badge-locked` 徽章与锁定提示条 |
| 4 | 测试用中文硬编码断言 UI 文案 | 测试客户端会带 `Accept-Language`，页面按 en 渲染而断言按 zh 取值 → 假失败 | 断言改为 `__()` 取词 + 类内固定 `X-Locale: zh_CN`，并在测试里写明原因 |
| 5 | 置顶逻辑散在 `PostService`，新的治理动作无处安放 | 版主操作会分散到多个服务 | 新增 `ModerationService`（置顶/加精/锁定/移版/恢复/回收站），`PostService` 只保留作者向操作 |
| 6 | 业务规则冲突（重复举报、结案过期、举报自己）按 `local.ERROR` + 堆栈写进日志 | 任何成员都能靠重复点击刷爆日志（掩盖真实告警、吃掉磁盘） | `dontReport(BusinessException::class)`：这类异常已渲染为 4xx，不再进错误日志；安全相关失败仍由各服务写显式审计。测试用 `Log::spy()` 断言"日志没有 error"，并已双向验证（去掉该行测试转红） |

### 治理功能的验证

```text
php artisan test
  Tests:    174 passed (699 assertions)     # 治理相关：ModerationTest 10 例、ReportTest 12 例、ModerationPagesTest 21 例
vendor/bin/phpstan analyse (level 6)
  [OK] No errors
```

真实 HTTP 冒烟（`.tmp/smoke-moderation.php`，双会话：版主 + 普通成员，**39 项断言全过**）：

```text
pages       治理台 200；普通成员访问治理台 403、访问自己的回收站 200
moderator   发帖 → 加精（徽章出现）→ 锁定（提示条出现、评论框消失）→ 移版 → 置顶；三条审计齐全
reporting   成员举报成功并入库 pending；页面转为"已举报"；重复举报只留一条；举报自己内容被拒
queue       队列显示标题/原因/备注；结案后 status=resolved 且记录处理人；再次结案跳回提示而非 500
trash       删除后详情 404 → 回收站可见 → 恢复 → 详情 200，并写入 forum.post.restored
PASS 39  FAIL 0
```

`vuln-lab` 对照漏洞：见 [forum-vuln-lab.md](forum-vuln-lab.md) 的 V22–V26（治理类）。

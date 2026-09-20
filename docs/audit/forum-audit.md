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

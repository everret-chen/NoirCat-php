# 论坛模块漏洞对照 / Forum (vuln-lab)

> ⚠️ 本分支**故意包含漏洞**，仅用于本地代码审计与渗透练习。
> **禁止**部署公网、**禁止**接入真实数据、**禁止**合并回 `main`。详见 [.github/SECURITY.md](../../.github/SECURITY.md)。

- **基线**：`main` 上已审计的论坛模块（含 Blade 前端与写接口的 `verified` 门槛），见 [forum-audit.md](forum-audit.md)
- **构造方式**：认证部分见 [auth-vuln-lab.md](auth-vuln-lab.md)（V1–V11）；论坛部分为本分支追加的 **V12–V21**，每处都带 `// VULN:` 注释便于定位
- **验证方式**：`php artisan test` → **38 个用例由绿转红**（本分支全量 115 例中 77 例仍通过），红的地方就是漏洞可被自动检测的证据

## 漏洞清单

| # | 漏洞 | 危害 | 位置 | 复现要点 | main 的防护 |
|---|---|---|---|---|---|
| V12 | 存储型 XSS（帖子正文） | 任意登录用户发帖即可在**所有读者**浏览器执行 JS：窃取 Cookie / 以读者身份发帖 | `MarkdownService` | 正文写入 `<script>alert(document.cookie)</script>` 或 `<img src=x onerror=...>`，详情页原样执行 | CommonMark `html_input=strip` + 禁不安全链接，再经 HTMLPurifier 白名单过滤 |
| V13 | 越权改帖 / 删帖 | 水平越权：改任意帖（含把他人草稿改成已发布）、删任意帖 | `PostPolicy::update/delete` | 用普通账号 `PUT/DELETE /api/posts/{他人帖子}` 返回 200 | 本人（需 `post:update_own`/`post:delete_own`）或版主（`post:delete_any`） |
| V14 | 草稿越权读取 | 未发布的草稿（含内部草稿）被列表中泄露，且列表与搜索都能带出 | `PostPolicy::view`、`PostService::paginate` | `GET /api/posts` 出现 `draft one`；`GET /api/posts/{草稿ID}` 返回 200 | 列表永远只查 `published()`；草稿仅作者与版主可见 |
| V15 | 评论越权删除 / 隐藏 | 删除/隐藏任意评论 = 内容擦除 + 舆论操控 | `CommentPolicy` | 普通账号 `DELETE /api/comments/{他人评论}`、`POST /api/comments/{id}/hide` 均 200 | 作者本人删自己的；隐藏需 `comment:delete_any` |
| V16 | 草稿可被评论 / 草稿评论串可读 | 给看不见的草稿灌内容、污染计数，并读到草稿下的讨论 | `PostPolicy::comment`、`CommentController`、`Web\ForumController` | 对他人草稿 `POST /api/posts/{草稿ID}/comments` 201；`GET .../comments` 200 | 仅已发布帖可评论；评论列表需 `view` 权限 |
| V17 | 评论嵌套深度绕过 | 递归渲染 + 深层嵌套造成页面/接口放大，可被用来打挂详情页 | `CommentService::create` | 连续回复到第 4、5 层仍 201 | 深度上限 3 层，超限 422/4001 |
| V18 | 计数可被操纵 | 榜单与热度数据失真：删/隐藏评论计数不减、重复点赞无限叠加 | `CommentService::delete/hide`、`PostService::like` | 同一点赞接口重复调用，`like_count` 从 1 涨到 2 | 计数只在首次创建点赞时自增；评论离开可见线程时同步减一 |
| V19 | 搜索通配符注入 | `%` 一次拉走全表（信息枚举 + 性能消耗） | `PostService::applySearch` | `GET /api/search?q=%%` 返回**全部**帖子 | 显式 `ESCAPE '!'`，`%`/`_` 按字面匹配 |
| V20 | 未验证邮箱可发帖 | 用一次性邮箱注册即可灌水、发广告；`verified` 承诺失效 | `routes/api.php`、`routes/web.php` | 新注册账号（未验证）直接发帖成功 | 写接口挂 `verified`：API `403/1005`，Web 跳个人页提示 |
| V21 | 浏览量可刷 | 热度与排序被脚本刷高（配合 V19 可放大） | `PostService::recordView` | 刷新详情页两次，`view_count` 变 2 | 每访客每帖每小时只计一次（`Cache::add`） |

> **V9 适配说明**：`main` 已把邮件里的验证链接改为指向 Blade 路由 `GET /email/verify/{id}/{hash}`，
> 本分支为保持 V9 可复现，**同时**去掉该 Blade 路由与 API 路由的 `signed` 中间件，
> 因此"未签名链接被拒"的用例（Web 端）也会转红。

## 被打红的用例（节选，共 38 例）

```text
FAILED  MarkdownServiceTest > raw html is stripped                      ← V12
FAILED  MarkdownServiceTest > unsafe link schemes are removed           ← V12
FAILED  MarkdownServiceTest > images cannot carry event handlers        ← V12
FAILED  MarkdownServiceTest > style and iframe payloads are dropped     ← V12
FAILED  PostTest > a member can publish a post and the html is cleaned   ← V12
FAILED  WebPagesTest > a verified member can publish and read it back   ← V12
FAILED  PostTest > only the author or a moderator can update a post     ← V13
FAILED  PostTest > only the author or a moderator can delete a post     ← V13
FAILED  WebPagesTest > a member cannot edit someone elses post          ← V13
FAILED  PostTest > drafts are hidden from the public listing            ← V14
FAILED  PostTest > a draft is only visible to its author and moderators ← V14
FAILED  SearchTest > search only returns published posts                ← V14
FAILED  CommentTest > only the author or a moderator can delete a c…    ← V15
FAILED  CommentTest > a moderator can hide a comment                    ← V15
FAILED  CommentTest > a draft does not accept comments                  ← V16
FAILED  CommentTest > the thread of a draft is not readable             ← V16
FAILED  WebPagesTest > a comment cannot be attached to a draft          ← V16
FAILED  CommentTest > nesting beyond the depth limit is rejected        ← V17
FAILED  CommentTest > deleting or hiding a comment keeps the counter…   ← V18
FAILED  PostTest > liking twice does not inflate the counter            ← V18
FAILED  SearchTest > search does not treat input as a wildcard pattern  ← V19
FAILED  WebPagesTest > an unverified member cannot write                ← V20
FAILED  PostTest > views are counted once per visitor                   ← V21
FAILED  WebPagesTest > an unsigned verification link is rejected        ← V9（适配）

（其余为认证模块 V1–V11 造成的红例，见 auth-vuln-lab.md）
Tests: 38 failed, 77 passed (397 assertions)
```

## 三类视角

1. **注入与输出编码**（V12 / V19）：一处是"存进去的 HTML 没过滤"，一处是"查出来的模式没转义"——
   都属于**把用户输入当成代码/语法**的经典错误，只是发生在不同层（输出层 vs 查询层）。
2. **授权边界**（V13 / V14 / V15 / V16）：共同点是"能访问接口"被误当成"有权操作这个对象"。
   论坛的自增主键让 ID 可枚举，缺少对象级授权就等于把草稿、他人评论暴露出去。
3. **状态与计数**（V17 / V18 / V21）：深度、计数、浏览量都是**服务端维护的状态**。
   只要某条路径忘了同步（删除、隐藏、重复请求），状态就会长期偏离事实，并被前端与排序放大。

## 练习建议

1. 用 curl / Burp 把每个漏洞打通，记录请求、响应与数据库前后的差异（尤其 `comment_count`、`like_count`）；
2. 回到 `main` 对照实现，写清"为什么这样写就防住了"，并指出防护落在哪一层（视图转义 / 策略 / 服务事务 / 查询语法）；
3. 尝试组合利用链：
   - V12 存储型 XSS → 偷管理员会话 → 配合 V13/V15 批量删帖删评论（完整的内容劫持链）；
   - V14 枚举草稿 ID + V19 通配符搜索 → 一次性dump 全站帖子标题；
   - V20 未验证邮箱批量注册 → V18 刷热度 + V21 刷浏览量 → 用假热度把广告帖顶到榜首；
   - V16 给他人草稿灌评论 → V18 让计数虚高 → 让作者的错误数据出现在前台。

## 覆盖缺口与改进记录

- 注入 V14（草稿越权）时，最初"搜索只返回已发布"没有变红，因为搜索用例造的数据里没有草稿；
  补上草稿数据后该用例转红，说明**负向用例必须覆盖所有查询入口**（列表、搜索、详情各算一个）。
- 注入 V19 时，`assert total = 0` 的正向/负向判断一开始写反，改成"命中数应为 0"后才有意义。
- 论坛的授权位点比认证多（帖子、评论、列表、搜索、评论串各自一处），**只改策略不改调用点**会留下漏洞：
  V16 正是"策略与控制器调用点都要补"的例子。

## 安全底线

- 只在本地/隔离环境运行：`php artisan serve --no-reload`
- 不要使用真实邮箱、真实密码或真实数据（V12 的 XSS 会真的执行）
- 修复只发生在 `main`；本分支的 `VULN` 注释与提交都不得进入 `main`

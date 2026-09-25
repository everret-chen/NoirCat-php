# 论坛模块漏洞对照 / Forum (vuln-lab)

> ⚠️ 本分支**故意包含漏洞**，仅用于本地代码审计与渗透练习。
> **禁止**部署公网、**禁止**接入真实数据、**禁止**合并回 `main`。详见 [.github/SECURITY.md](../../.github/SECURITY.md)。

- **基线**：`main` 上已审计的论坛模块（含 Blade 前端、写接口的 `verified` 门槛，以及治理环路：版主动作 / 举报队列 / 回收站），见 [forum-audit.md](forum-audit.md)
- **构造方式**：认证部分见 [auth-vuln-lab.md](auth-vuln-lab.md)（V1–V11）；论坛内容部分是 **V12–V21**；`main` 新增治理环路后在本分支追加 **V22–V26**，每处都带 `// VULN:` 注释便于定位
- **验证方式**：`php artisan test` → **63 个用例由绿转红**（本分支全量 173 例中 110 例仍通过）：其中 V1–V21 造成 49 例，治理类 V22–V26 新增 14 例，红的地方就是漏洞可被自动检测的证据

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
| V22 | 举报防刷失效 | 一个账号可以把同一条内容重复举报到底，用重复行把举报队列淹没，真人举报被埋 | `ReportService::create`（重复预检）、`2026_09_21_100300_drop_reports_unique_index` | 同一 token 对同一帖子连续 `POST /api/reports` 均 201，`reports` 表出现多行相同 `(reporter_id, reportable_type, reportable_id)` | 服务层 `alreadyReported()` 预检返回 409，外加 `reports_reporter_unique` 唯一索引兜底（应用层失效时数据库仍然拦住） |
| V23 | 举报对象不受限 / 可举报自己 | 任意模型都能被"举报"（含 `User`），队列里出现不可处理的对象；自己举报自己可以反复刷；举报功能被当成垃圾数据入口 | `ReportService::create`（`REPORTABLE` 白名单 + 自举报校验）、`StoreReportRequest` | `reportable_type=user` 举报一个用户返回 201；作者举报自己的帖子返回 201 | 白名单只允许 `Post`/`Comment`（422 `report_unsupported`），且 `authorIdOf() === reporter->id` 时 422 `report_own` |
| V24 | 治理动作越权 | 普通成员可以加精 / 锁帖 / 移动版块（编辑权 + 打断讨论），还能读取整个举报队列（谁举报了谁、为什么），并直接结案清空队列 | `PostPolicy::feature/lock/move`、`ReportPolicy::viewAny/handle` | 普通成员 `POST /api/posts/{id}/feature`、`/lock`、`PUT .../category` 均 200；`GET /api/reports` 200；`POST /api/reports/{id}/dismiss` 200 | 三个动作分别校验 `post:feature` / `post:lock` / `post:move`；读队列与结案校验 `report:handle`，403 |
| V25 | 回收站越权 | 回收站不再按作者过滤，普通成员能看到**所有人**被删的内容（含版主下架的帖子），再配合无条件的 `restore` 把被处理掉的内容复原 | `ModerationService::trashPosts/trashComments`、`PostPolicy::restore`、`CommentPolicy::restore` | 普通成员 `GET /api/moderation/trash` 出现他人删除的帖子；`POST /api/posts/{他人帖子}/restore` 200 | 查询按 `post:delete_any` / `comment:delete_any` 过滤（无权限只看自己的）；`restore` 与删除同权限 |
| V26 | 锁定形同虚设 | 版主"锁帖"后仍可被回帖，锁帖作为应急处置（中止骂战 / 停止谣言扩散）完全失效 | `PostPolicy::comment`、`CommentService::create` | 版主锁帖后，普通成员 `POST /api/posts/{id}/comments` 返回 **201** 且评论入库（`main` 为 403） | 策略 `isPublished() && ! isLocked()`，服务层再兜一次 `post_locked` 422（纵深防御） |

> **V9 适配说明**：`main` 已把邮件里的验证链接改为指向 Blade 路由 `GET /email/verify/{id}/{hash}`，
> 本分支为保持 V9 可复现，**同时**去掉该 Blade 路由与 API 路由的 `signed` 中间件，
> 因此"未签名链接被拒"的用例（Web 端）也会转红。

> **V16 / V26 关系说明**：V16 已让 `PostPolicy::comment` 无条件返回 `true`，所以"锁帖"从 V16 起就已经失效；
> V26 进一步删掉 `CommentService::create` 里的 `is_locked()` 兜底，锁帖帖子的回帖从"422 被拒"变成"**201 入库**"。
> 这正是纵深防御被逐层拆掉的过程：**策略层**与**服务层**各自都要有判断，任何一层缺席都会让"版主已锁帖"这句话变成谎话。

## 被打红的用例（共 63 例：V1–V21 造成 49 例，V22–V26 新增 14 例）

```text
# V12 输出层 / 渲染（6 例）
FAILED  MarkdownServiceTest > raw html is stripped                      ← V12
FAILED  MarkdownServiceTest > unsafe link schemes are removed           ← V12
FAILED  MarkdownServiceTest > images cannot carry event handlers        ← V12
FAILED  MarkdownServiceTest > style and iframe payloads are dropped     ← V12
FAILED  PostTest > a member can publish a post and the html is sanitised ← V12
FAILED  WebPagesTest > a verified member can publish and read it back   ← V12
# V13 越权改/删
FAILED  PostTest > only the author or a moderator can update a post     ← V13
FAILED  PostTest > only the author or a moderator can delete a post     ← V13
FAILED  WebPagesTest > a member cannot edit someone elses post          ← V13
# V14 草稿越权读取
FAILED  PostTest > drafts are hidden from the public list               ← V14
FAILED  PostTest > a draft is only visible to its author and moderators ← V14
FAILED  SearchTest > search only returns published posts                ← V14
# V15 评论越权
FAILED  CommentTest > only the author or a moderator can delete a c…    ← V15
FAILED  CommentTest > a moderator can hide a comment                    ← V15
FAILED  ModerationPagesTest > a member cannot hide someone elses comment ← V15
# V16 草稿可评论
FAILED  CommentTest > a draft does not accept comments                  ← V16
FAILED  CommentTest > the thread of a draft is not readable             ← V16
FAILED  WebPagesTest > a comment cannot be attached to a draft          ← V16
# V17 / V18 / V19 / V20 / V21
FAILED  CommentTest > nesting beyond the depth limit is rejected        ← V17
FAILED  CommentTest > deleting or hiding a comment keeps the counter…   ← V18
FAILED  PostTest > liking twice does not inflate the counter            ← V18
FAILED  ModerationTest > hidden and deleted comments can be brought back ← V18
FAILED  ModerationPagesTest > a moderator can hide and unhide a comment from the thread ← V18
FAILED  ModerationPagesTest > the trash restores a deleted comment and its counter ← V18
FAILED  SearchTest > search does not treat input as a wildcard pattern  ← V19
FAILED  WebPagesTest > an unverified member cannot write                ← V20
FAILED  VerifiedGateTest > the gate is on by default                    ← V20（main 新增用例）
FAILED  VerifiedGateTest > production ignores the switch                ← V20（main 新增用例）
FAILED  PostTest > views are counted once per visitor                   ← V21
FAILED  WebPagesTest > an unsigned verification link is rejected        ← V9（适配）
# 回收站 / 恢复：V13、V15 的无条件策略先让它们转红，V25 再让成员看到别人的删除
FAILED  ModerationTest > an author can restore their own post but not someone elses ← V13/V25
FAILED  ModerationTest > a member cannot restore a comment they do not own ← V15/V25
FAILED  ModerationPagesTest > the trash cannot be used to restore someone elses content ← V13/V25
# V26 锁帖失效
FAILED  ModerationTest > a locked thread stops accepting comments       ← V26（422 → 201）
FAILED  ModerationPagesTest > a locked thread hides the comment form    ← V26（POST 半段 403 → 302 并入库）
# V22 举报防刷失效
FAILED  ReportTest > reporting the same content twice is rejected        ← V22（409 → 201）
FAILED  ModerationPagesTest > a member reports a post from the page      ← V22（重复举报不再提示）
# V23 举报对象不受限 / 可举报自己
FAILED  ReportTest > you cannot report your own content                  ← V23（422 → 201）
FAILED  ReportTest > an unsupported target is rejected                   ← V23（422 → 201，可举报 User）
FAILED  ModerationPagesTest > a member cannot report their own post      ← V23（自举报落库）
# V24 治理动作越权
FAILED  ModerationTest > a member cannot feature lock or move             ← V24（403 → 200）
FAILED  ReportTest > only handlers can read the queue                     ← V24（403 → 200）
FAILED  ReportTest > a member cannot close a report                       ← V24（403 → 200）
FAILED  ModerationPagesTest > a member is denied the report queue…        ← V24（403 → 200）
FAILED  ModerationPagesTest > a member cannot close a report              ← V24（403 → 302）
FAILED  ModerationPagesTest > a member cannot use the moderator actions   ← V24（403 → 302）
FAILED  ModerationPagesTest > an ordinary member does not see the toolbar ← V24（前端也把版主工具条露给成员）
FAILED  ModerationPagesTest > the navigation only shows governance links to moderators ← V24
# V25 回收站越权
FAILED  ModerationTest > trash lists a members own deletions only         ← V25（列出他人删除的帖子）

（其余 14 例为认证模块 V1–V11 造成的红例，见 auth-vuln-lab.md）
Tests: 63 failed, 110 passed (576 assertions)
```

## 三类视角

1. **注入与输出编码**（V12 / V19）：一处是"存进去的 HTML 没过滤"，一处是"查出来的模式没转义"——
   都属于**把用户输入当成代码/语法**的经典错误，只是发生在不同层（输出层 vs 查询层）。
2. **授权边界**（V13 / V14 / V15 / V16）：共同点是"能访问接口"被误当成"有权操作这个对象"。
   论坛的自增主键让 ID 可枚举，缺少对象级授权就等于把草稿、他人评论暴露出去。
3. **状态与计数**（V17 / V18 / V21）：深度、计数、浏览量都是**服务端维护的状态**。
   只要某条路径忘了同步（删除、隐藏、重复请求），状态就会长期偏离事实，并被前端与排序放大。

> **延伸：治理类（V22–V26）属于第三类与第二类的交叉**。举报队列、回收站、版主动作是社区里**权力最集中的地方**，
> 它们本身也是攻击面：举报可以刷（V22）、可以指向任意对象（V23）；权限判定一旦被改成恒真，
> 普通成员就能加精锁帖、翻看举报人并结案（V24）；回收站不再按作者过滤，等于把"删除"变成"下架到公共列表"（V25）；
> 锁帖失效则让应急处置形同虚设（V26）。共同点是**把治理接口当成普通接口来写**。

## 练习建议

1. 用 curl / Burp 把每个漏洞打通，记录请求、响应与数据库前后的差异（尤其 `comment_count`、`like_count`、`reports` 的行数）；
2. 回到 `main` 对照实现，写清"为什么这样写就防住了"，并指出防护落在哪一层（视图转义 / 策略 / 服务事务 / 查询语法 / 数据库约束）；
3. 尝试组合利用链：
   - V12 存储型 XSS → 偷管理员会话 → 配合 V13/V15 批量删帖删评论（完整的内容劫持链）；
   - V14 枚举草稿 ID + V19 通配符搜索 → 一次性dump 全站帖子标题；
   - V20 未验证邮箱批量注册 → V18 刷热度 + V21 刷浏览量 → 用假热度把广告帖顶到榜首；
   - V16 给他人草稿灌评论 → V18 让计数虚高 → 让作者的错误数据出现在前台；
   - **治理链**：V23 用任意账号/对象刷举报 + V22 反复提交同一条 → 队列被噪声淹没，版主漏看真举报；
     V24 用普通成员身份直接读队列拿到举报人名单（社工/报复），再把真举报 dismiss 掉清空痕迹；
   - **删除与复原链**：V13/V15 先删掉别人的内容 → V25 在回收站里把**所有人**被删的内容（含版主下架的）翻出来，
     再用无条件的 `restore` 把它们复原；若配合 V12 的 XSS，被复原的恶意帖会重新出现在首页；
   - **锁帖绕过链**：V26 让版主锁帖失效，正在被处理的骂战可以继续回帖（若同时有 V18，评论计数还会继续漂移）。

## 覆盖缺口与改进记录

- 注入 V14（草稿越权）时，最初"搜索只返回已发布"没有变红，因为搜索用例造的数据里没有草稿；
  补上草稿数据后该用例转红，说明**负向用例必须覆盖所有查询入口**（列表、搜索、详情各算一个）。
- 注入 V19 时，`assert total = 0` 的正向/负向判断一开始写反，改成"命中数应为 0"后才有意义。
- 论坛的授权位点比认证多（帖子、评论、列表、搜索、评论串各自一处），**只改策略不改调用点**会留下漏洞：
  V16 正是"策略与控制器调用点都要补"的例子。
- 治理环路带来了**两层防护**：V22 必须同时删掉服务层的 `alreadyReported()` 预检**和**数据库唯一索引才会转红，
  只删一层时唯一索引会抛异常（500 而不是 201），用例照样红但复现方式完全不同——
  这说明"应用层校验 + 数据库约束"是有意冗余的，审计时两层都要看。
- V25/V26 提醒：**策略（授权）和服务（业务规则）不是同一件事**。回收站既要按作者过滤查询，
  也要在 `restore` 上校验权限；锁帖既要策略拦、也要服务兜底。用例的写法也区分了这一点：
  一条用例断言"看不到别人的删除"，另一条断言"恢复别人的内容被拒"。
- V24 的副作用值得记录：策略恒真不仅让 API 200，连 Blade 里的工具条与导航也一起露给了普通成员
  （`an ordinary member does not see the toolbar`、`the navigation only shows governance links to moderators` 转红）——
  **前端可见性与后端授权共用同一套策略**，改一处会同时影响两者。

## 安全底线

- 只在本地/隔离环境运行：`php artisan serve --no-reload`
- 不要使用真实邮箱、真实密码或真实数据（V12 的 XSS 会真的执行）
- 不要在真实社区里用治理接口做实验：V22–V26 会污染举报队列与回收站，删掉的内容可能被复原
- 修复只发生在 `main`；本分支的 `VULN` 注释与提交都不得进入 `main`

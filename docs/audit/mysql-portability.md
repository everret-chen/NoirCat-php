# MySQL 8 可移植性审计 / Portability audit

- **范围**：`database/migrations/`、`app/`、`routes/`、`config/database.php`、`.env.example`、`phpunit.xml`、CI
- **背景**：开发期用 SQLite（`database/database.sqlite`），Phase 3 起目标数据库是 MySQL 8
- **结论**：**代码侧的问题已修**（见第 2 节）；本机**无法**完成真实 MySQL 验证，缺口与需要你动手的两步写在第 4 节
- **验证方式**：CI 新增 `Tests against MySQL 8` 任务（MySQL 8 service container + `migrate:fresh` + 全量测试 + 表数量断言）

## 1. 检查清单与结论

| 检查项 | 结论 | 落点 |
|---|---|---|
| PHP 有 MySQL 驱动 | ❌ **本机没有**：`php.ini` 只启用了 `pdo_sqlite`（第 16 行），`ext\php_pdo_mysql.dll` 存在但未启用 | 需你改 `E:\php83\php.ini` |
| MySQL 服务可达 | ❌ 本机没有 `mysql` CLI、没有 Docker、3306 未监听 | 需你安装或起容器 |
| 长正文列是否会溢出 | ⚠️ **会**：MySQL `TEXT` 上限 65,535 **字节**，而校验允许 50,000 **字符**（中文约 3 字节/字）→ 1406 错误 = 今天能发的帖子 500 | 已修：新增迁移改 `MEDIUMTEXT` |
| 字符串路由参数与 BIGINT 主键比较 | ⚠️ **会**：MySQL 把 `12abc` 强转成 12，`/forum/12abc` 在 MySQL 返回 12 号帖、SQLite 返回 404 | 已修：全局 `Route::pattern()` 只允许数字 |
| 排序并列导致分页重复/漏行 | ⚠️ 会：`created_at` 只有秒级精度，且两个引擎对并列行的顺序不同 | 已修：所有排序补 `id` 兜底 |
| 外键是否真的生效 | ⚠️ `engine => null` 会跟随服务器默认；若为 MyISAM，外键被静默忽略 | 已修：`DB_ENGINE=InnoDB` |
| 时区一致性 | ⚠️ mysql 连接块没有 `timezone`，会话时区随服务器（通常 UTC），而 `APP_TIMEZONE=Asia/Shanghai` | 已修：`DB_TIMEZONE=+08:00` |
| 排序规则（大小写/空格/emoji） | ⚠️ `utf8mb4_unicode_ci` 会：`Alice`＝`alice`、`'alice'`＝`'alice '`、`'😀'`＝`'😁'` | 已改默认 `utf8mb4_0900_ai_ci`（NO PAD、不折叠补充平面字符） |
| 索引字节上限（191/3072） | ✅ 全部合规：最大是 `permissions(name, guard_name)` 2040 B ≤ 3072 B（InnoDB DYNAMIC） | 参见 `create_permission_tables.php` 注释 |
| TEXT/BLOB 默认值 | ✅ 无一处对 TEXT/BLOB/JSON 使用 `->default()` | — |
| `lockForUpdate()` / SQL 级 `groupBy` | ✅ 全项目没有，`ONLY_FULL_GROUP_BY` 不会踩 | — |
| JSON 列 | ✅ 只有 `audit_logs.payload`，模型用 `array` cast；MySQL 会额外做写入校验与键序规范化 | 见第 3 节 R 列表 |

## 2. 已修复的问题

| # | 问题 | 风险 | 处理 |
|---|---|---|---|
| 1 | `posts.content` / `content_html` 是 `TEXT`，校验上限 50,000 字符 | 中文长帖在 MySQL 触发 1406 → 500，且 `content_html` 比原文更大 | 新增迁移 `2026_09_21_100200_widen_post_body_columns` 改为 `MEDIUMTEXT`（16 MB），校验规则不变；历史迁移按 AGENTS.md 不动 |
| 2 | 路由参数没有数字约束 | `DELETE /api/auth/sessions/3abc` 在 MySQL 上会注销 3 号令牌、`/email/verify/5abc/{hash}` 会验证 5 号用户；SQLite 上都是 404 | `AppServiceProvider::configureRoutePatterns()` 为 `post/comment/report/category/session/id` 统一加 `[0-9]+` |
| 3 | 多个排序只按非唯一列 | 并列行的顺序在两引擎不同，分页会重复或漏行 | `PostService::paginate`（4 个分支）、`Post::scopePinnedFirst`、评论列表、举报队列全部补 `orderBy('id')` 兜底 |
| 4 | `engine => null` | 服务器默认存储引擎若是 MyISAM，posts/comments/likes/reports 的外键会被静默忽略 | `DB_ENGINE`，默认 `InnoDB` |
| 5 | mysql 连接无 `timezone` | `TIMESTAMP` 列按会话时区↔UTC 转换，`CURRENT_TIMESTAMP` 默认值也按会话时区打戳 → 同表混两种时钟 | `DB_TIMEZONE`，默认 `+08:00`，与 `APP_TIMEZONE` 对齐 |
| 6 | 默认 `utf8mb4_unicode_ci` | 大小写/尾随空格/emoji 在内的比较语义比 SQLite 宽松，会出现"MySQL 能登录、SQLite 不能"这类分叉 | 默认改为 MySQL 8 的 `utf8mb4_0900_ai_ci`；`.env.example` 里写明原因 |
| 7 | 测试只跑 SQLite | 上面这些问题在 CI 里永远不会失败 | CI 新增 `Tests against MySQL 8` 任务：MySQL 8 service + `migrate:fresh --seed` + 全量测试 + "表确实建在 MySQL 里"的断言 |

## 3. 明确保留的行为差异（未改代码，已记录）

| 编号 | 差异 | 为什么先不改 |
|---|---|---|
| R1 | 邮箱/用户名比较在 MySQL 上大小写不敏感（`utf8mb4_0900_ai_ci`），SQLite 上敏感 | 应用层统一小写（`Str::lower`）属于"邮箱硬化"批次，与"改邮箱后验证失效""登录大小写"一起做更省事；`docs/audit/phases.md` 待办里有记录 |
| R2 | `PAD SPACE` 差异（尾随空格） | 同上，随写入侧 trim 一起处理 |
| R4 | MySQL 的 `LIKE` 对所有文字都不区分大小写与重音，SQLite 只对 ASCII 不区分 | 搜索将来换 Meilisearch，届时语义由索引决定；当前测试用例不翻转 |
| R7 | `TIMESTAMP` 2038 上限 | 当前没有任何业务需要 2038 之后的时间；真需要时把那几列改成 `dateTime()` |
| R10 | MySQL `JSON` 列会校验写入并规范化键序 | 只影响 `audit_logs.payload`；已有 `array` cast，且项目不做 payload 字符串比较 |

## 4. 本机完成真实验证还差两步（需要你操作）

1. **启用 PHP 的 MySQL 驱动**（文件在工作区之外，我不能改）：

   ```ini
   ; E:\php83\php.ini
   extension=pdo_mysql
   ```

   然后确认：`E:\php83\php.exe -m | findstr pdo_mysql`

2. **准备一个 MySQL 8**（本机没有 CLI、没有 Docker、3306 未监听）：装 MySQL 8 服务，或安装 Docker 后起一个容器：

   ```bash
   docker run --name noircat-mysql -e MYSQL_ROOT_PASSWORD=root \
     -e MYSQL_DATABASE=noircat -p 3306:3306 -d mysql:8.0
   ```

3. 切换并验证（`.env` 里的模板已经写在 `DB_CONNECTION=sqlite` 上面）：

   ```bash
   php artisan migrate:fresh --seed --force
   php artisan test
   ```

   在没有 MySQL 之前，CI 的 `Tests against MySQL 8` 任务就是唯一的真实验证途径。

## 5. 复盘

1. **开发数据库和部署数据库不同，等于没有验证过 schema**：SQLite 对列长、类型、排序规则都很宽容，这类问题只会在生产第一次写长文本时爆出来。
2. **"能跑" 不等于 "语义相同"**：强转、排序规则、时区这些差异不会报错，只会悄悄改变结果；把它们写进 CI 才有意义。
3. **先取证再改**：本次结论都来自实际读代码与 `php -m` 输出，而不是"MySQL 一般会怎样"。

# 贡献指南 / Contributing

墨猫问雪 / NoirCat 是中英双语网络安全学习社区，同时是一个 PHP 代码审计练习项目。
本文件说明协作方式；代码级约定见 [AGENTS.md](AGENTS.md)，方案见 [PROJECT_SPEC_PHP.md](PROJECT_SPEC_PHP.md) 与 [docs/PLAN_V2.md](docs/PLAN_V2.md)。

## 分支模型

| 分支 | 用途 |
|---|---|
| `main` | 安全版。只接收已审计、已通过 CI 的合并，**不直接在上面开发** |
| `dev` | 日常开发集成分支 |
| `vuln-lab` | 漏洞版。从 `main` 派生，按模块注入经典漏洞，**永不合并回 `main`** |
| `feature/*` | 单个功能/修复，合回 `dev` |

```bash
git checkout dev && git pull origin dev
git checkout -b feature/forum-post
# 写代码…
git add .
git commit -m "feat(forum): add post list page"
git push origin feature/forum-post
# 开 PR → 合并到 dev
```

## 提交信息

遵循 Conventional Commits，**描述用英文**：

```text
feat(forum): add post list page
fix(auth): reject expired sanctum tokens
docs(audit): add forum audit report
refactor(core): extract api response envelope
chore(deps): bump laravel framework
```

常用 scope：`core` / `auth` / `forum` / `books` / `learn` / `guild` / `plugin` / `ci` / `docs`。

## 代码约定

- PHP 8.3+，**PSR-12**，**4 空格缩进**，2 空格用于 YAML
- 控制器瘦、服务层厚：业务逻辑放 `app/Services`
- 所有 SQL 用 Eloquent 或 Query Builder 参数绑定，禁止字符串拼接 SQL
- 不使用 `DB::` facade 直连（插件场景走受控代理）
- 文件读写用 Laravel Filesystem，不直接操作文件系统
- 回复与文档用中文，**代码注释用英文**

## 本地环境

要求 PHP 8.3+ 与 Composer 2：

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve      # http://localhost:8000
```

开发期数据库是 SQLite（`database/database.sqlite`），会话/队列/缓存均用 `database` 驱动，
不需要 Docker、MySQL、Redis 即可跑通。

### Composer 注册表说明

本仓库的 `composer.lock` 是通过**腾讯云镜像**解析的（项目主要开发环境在无法直连
GitHub 的网络中）。因此：

- `.github/workflows/ci.yml` 会配置同一个镜像，保持 lock 的 content hash 一致；
- 如果你在可直连 GitHub 的网络中开发，想切回官方源：

  ```bash
  composer config -g repos.packagist composer https://repo.packagist.org
  rm composer.lock vendor -rf
  composer update
  ```

  切回后请提交新的 `composer.lock`，并注意会与国内开发环境产生冲突（只能二选一）。

## 测试与静态分析

提交前必须全绿：

```bash
php artisan test                       # PHPUnit，测试库为内存 SQLite
vendor/bin/phpstan analyse             # larastan + PHPStan level 6
composer audit                         # 依赖漏洞扫描
```

新增功能请带测试：每个模块至少 3 个用例（见 `PROJECT_SPEC_PHP.md` 第 7 节）。

## 安全审计要求

每个模块完成后，除了功能代码，还要在 `vuln-lab` 分支注入对应漏洞并撰写审计报告：

- 报告路径：`docs/audit/{module}-audit.md`
- 内容：发现的问题、漏洞原理、修复方案（对应 `main` 的提交）、验证过程、复盘
- 同时更新漏洞总索引与 `docs/audit/phases.md`

**底线**：`vuln-lab` 只在本地/隔离环境运行，禁止部署公网、禁止接入真实数据。

## 不要提交

`.env`、`vendor/`、`node_modules/`、`.tmp/`、`database/*.sqlite`（均已在 `.gitignore` 中）。

## 报告安全问题

见 [.github/SECURITY.md](.github/SECURITY.md)。**`vuln-lab` 上的漏洞不是漏洞**，请勿作为安全问题上报。

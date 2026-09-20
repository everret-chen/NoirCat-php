## 变更内容

<!-- 简要说明这个 PR 做了什么、为什么 -->

## 涉及模块

- [ ] 认证与权限
- [ ] 论坛
- [ ] 电子书
- [ ] 学习成长
- [ ] 公会与插件
- [ ] 核心 / 基础设施

## 约定检查

- [ ] 遵循 PSR-12（4 空格缩进），控制器瘦、服务层厚
- [ ] 所有 SQL 走 Eloquent / Query Builder 参数绑定
- [ ] 未修改 `database/migrations/` 下的历史迁移文件
- [ ] 未提交 `.env` / `vendor/` / `node_modules`
- [ ] `php artisan test` 通过
- [ ] `vendor/bin/phpstan analyse` 无错误
- [ ] 已更新 `docs/audit/phases.md`

## 安全自查（对照 PROJECT_SPEC_PHP 第 6 节）

- [ ] 输入校验（FormRequest）与输出转义
- [ ] 授权检查（Policy / 权限点），无越权（IDOR）
- [ ] Markdown / HTML 渲染经 HTML Purifier 过滤
- [ ] 文件上传校验类型与大小，禁止可执行文件
- [ ] 敏感操作写入 `audit_logs`，且敏感字段已脱敏
- [ ] 生产环境错误信息不泄露堆栈（`APP_DEBUG=false`）
- [ ] `vuln-lab` 的漏洞代码未混入 `main`

## 审计产出（新模块必填）

- [ ] `docs/audit/{module}-audit.md` 已撰写（含问题、原理、修复、验证）

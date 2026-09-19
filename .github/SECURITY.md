# 安全政策 / Security Policy

## 报告真实漏洞

如果你在 **`main` 分支**（安全版）发现真实安全问题，请**不要**公开开 issue：

- 优先使用 GitHub 的 **Private vulnerability reporting**（仓库 Security 标签页）
- 或直接联系维护者：[@everret-chen](https://github.com/everret-chen)

我们会在 7 天内确认收到，并在修复后于提交信息中致谢（如你希望署名）。

## ⚠️ `vuln-lab` 分支不是产品代码

本仓库是网络安全学习项目，采用双分支策略：

| 分支 | 定位 |
|---|---|
| `main` | 安全版，正常接收漏洞修复 |
| `dev` | 日常开发 |
| `vuln-lab` | **故意保留**经典 Web 漏洞，用于代码审计与渗透练习 |

`vuln-lab` 中**故意存在**以下类型的漏洞（不限于）：

- SQL 注入（手写拼接查询）
- 存储型 XSS（绕过 HTML Purifier）
- 任意文件上传 / 路径穿越
- 越权访问 IDOR（水平与垂直）
- CSRF、SSRF、SSTI、XXE
- 认证缺陷（爆破无限制、会话固定、JWT `alg=none`、弱随机）
- 业务逻辑漏洞（刷分、跳步、积分竞态）
- 公会插件沙箱逃逸

因此：

1. **禁止**把 `vuln-lab` 部署到公网或任何他人可访问的环境；
2. **禁止**在 `vuln-lab` 中使用真实用户数据、真实凭据或生产数据库；
3. **禁止**把 `vuln-lab` 的代码合并回 `main`；
4. 在 `vuln-lab` 上发现的"漏洞"**不是漏洞**，不需要修复——修复只发生在 `main`。

## 支持范围

仅当前 `main` 分支（最新提交）接受安全修复。历史版本不单独维护。

## 安全基线

`main` 分支遵循 `PROJECT_SPEC_PHP.md` 第 6、9 节的要求，包括：

- 所有 API 需 Sanctum 认证（公共接口除外）
- 输入经 FormRequest 校验，输出经 Blade 转义 / HTML Purifier 过滤
- 所有 SQL 使用 Eloquent 或 Query Builder 参数绑定
- 文件上传校验类型与大小，禁止可执行文件
- 敏感操作写入 `audit_logs`，且敏感字段脱敏
- 插件运行在受控沙箱中（能力白名单）
- `.env` 不入库；依赖经 `composer audit` 扫描

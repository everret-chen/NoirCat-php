# NoirCat PHP 项目指令

## 项目
墨猫问雪 / NoirCat，中英双语网络安全学习社区。
技术栈见 PROJECT_SPEC_PHP.md。

## 代码约定
- PHP 8.3+，PSR-12，4 空格缩进
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
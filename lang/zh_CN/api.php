<?php

declare(strict_types=1);

return [
    'errors' => [
        // 1xxx authentication
        'UNAUTHENTICATED' => '请先登录',
        'TOKEN_EXPIRED' => '登录状态已过期，请重新登录',
        'INVALID_CREDENTIALS' => '用户名或密码错误',
        'ACCOUNT_DISABLED' => '账号已被禁用',
        'EMAIL_NOT_VERIFIED' => '请先完成邮箱验证',
        'VERIFICATION_LINK_INVALID' => '验证链接无效或已过期',

        // 2xxx authorization
        'FORBIDDEN' => '没有权限执行该操作',
        'INSUFFICIENT_ROLE' => '当前角色权限不足',

        // 3xxx validation and resources
        'VALIDATION_FAILED' => '提交的数据不合法',
        'RESOURCE_NOT_FOUND' => '请求的资源不存在',
        'ROUTE_NOT_FOUND' => '请求的接口不存在',
        'METHOD_NOT_ALLOWED' => '请求方法不被允许',

        // 4xxx business rules
        'BUSINESS_RULE_VIOLATION' => '操作不满足业务规则',

        // 5xxx system
        'RATE_LIMITED' => '请求过于频繁，请稍后再试',
        'SYSTEM_ERROR' => '服务器内部错误',
        'SERVICE_UNAVAILABLE' => '服务暂时不可用',
    ],

    'messages' => [
        'registered' => '注册成功',
        'logged_in' => '登录成功',
        'logged_out' => '已退出登录',
        'profile_updated' => '资料已更新',
        'avatar_updated' => '头像已更新',
        'password_reset_link_sent' => '如果该邮箱已注册，我们已发送重置链接',
        'password_reset' => '密码已重置，请重新登录',
        'verification_sent' => '验证邮件已发送',
        'email_verified' => '邮箱验证成功',
        'already_verified' => '邮箱已经验证过了',
        'session_revoked' => '该登录设备已下线',
        'sessions_revoked' => '其它登录设备已全部下线',
    ],
];

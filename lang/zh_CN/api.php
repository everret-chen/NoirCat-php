<?php

declare(strict_types=1);

return [
    'errors' => [
        // 1xxx authentication
        'UNAUTHENTICATED' => '请先登录',
        'TOKEN_EXPIRED' => '登录状态已过期，请重新登录',
        'INVALID_CREDENTIALS' => '用户名或密码错误',
        'ACCOUNT_DISABLED' => '账号已被禁用',

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
];

<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Validation messages (zh_CN)
|--------------------------------------------------------------------------
|
| A practical subset is translated here; any key missing falls back to
| config('app.fallback_locale') (en), so English stays available for the
| rules that are not covered yet.
|
*/

return [
    'accepted' => ':attribute 必须接受。',
    'after' => ':attribute 必须晚于 :date。',
    'alpha_dash' => ':attribute 只能包含字母、数字、下划线和短横线。',
    'array' => ':attribute 必须是数组。',
    'before' => ':attribute 必须早于 :date。',
    'boolean' => ':attribute 必须为 true 或 false。',
    'confirmed' => ':attribute 与确认值不一致。',
    'current_password' => '当前密码不正确。',
    'date' => ':attribute 不是有效日期。',
    'different' => ':attribute 与 :other 必须不同。',
    'email' => ':attribute 必须是有效的邮箱地址。',
    'exists' => '所选 :attribute 无效。',
    'filled' => ':attribute 不能为空。',
    'image' => ':attribute 必须是图片。',
    'in' => '所选 :attribute 无效。',
    'integer' => ':attribute 必须是整数。',
    'max' => [
        'array' => ':attribute 最多 :max 项。',
        'file' => ':attribute 不能大于 :max KB。',
        'numeric' => ':attribute 不能大于 :max。',
        'string' => ':attribute 不能超过 :max 个字符。',
    ],
    'mimes' => ':attribute 必须是 :values 类型的文件。',
    'min' => [
        'array' => ':attribute 至少 :min 项。',
        'file' => ':attribute 不能小于 :min KB。',
        'numeric' => ':attribute 不能小于 :min。',
        'string' => ':attribute 至少 :min 个字符。',
    ],
    'not_in' => '所选 :attribute 无效。',
    'numeric' => ':attribute 必须是数字。',
    'regex' => ':attribute 格式不正确。',
    'required' => ':attribute 不能为空。',
    'required_if' => '当 :other 为 :value 时，:attribute 不能为空。',
    'same' => ':attribute 与 :other 必须一致。',
    'size' => [
        'array' => ':attribute 必须包含 :size 项。',
        'file' => ':attribute 必须为 :size KB。',
        'numeric' => ':attribute 必须等于 :size。',
        'string' => ':attribute 必须为 :size 个字符。',
    ],
    'string' => ':attribute 必须是字符串。',
    'unique' => ':attribute 已被使用。',
    'uploaded' => ':attribute 上传失败。',
    'url' => ':attribute 必须是有效的链接。',
    'uuid' => ':attribute 必须是有效的 UUID。',

    'custom' => [],

    'attributes' => [
        'username' => '用户名',
        'email' => '邮箱',
        'password' => '密码',
        'password_confirmation' => '确认密码',
        'avatar' => '头像',
        'title' => '标题',
        'content' => '内容',
        'role' => '角色',
        'page' => '页码',
        'limit' => '每页数量',
    ],
];

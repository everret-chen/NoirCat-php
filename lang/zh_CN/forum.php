<?php

declare(strict_types=1);

return [
    'messages' => [
        'post_created' => '发帖成功',
        'post_updated' => '帖子已更新',
        'post_deleted' => '帖子已删除',
        'post_pinned' => '帖子已置顶',
        'post_unpinned' => '已取消置顶',
        'post_liked' => '已点赞',
        'post_unliked' => '已取消点赞',
        'comment_created' => '回复成功',
        'comment_deleted' => '评论已删除',
        'comment_hidden' => '评论已隐藏',
    ],
    'errors' => [
        'post_not_found' => '帖子不存在',
        'comment_not_found' => '评论不存在',
        'max_depth' => '回复层级过深，请回复到上一层',
        'draft_visible_to_author' => '草稿仅作者与管理员可见',
    ],
];

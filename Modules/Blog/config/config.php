<?php

return [
    'name' => 'Blog',
    'cache' => [

        "blog_post_name" => "BlogPost",
        "blog_post_category_name" => "BlogPostCategory",
        "blog_category_name" => "BlogCategory",
        'name' => 'Blog',
        "blog_post_comment_name" => "BlogPostComment",
    ],
    "blog_category_name" => "BlogCategory",
    "blog_post_name" => "BlogPost",
    'comment_status' => [
        'pending'   =>  1,
        'approved'  =>  2,
        'rejected'  =>  3
    ],
    'comment_status_value' => [
        'pending'   =>  'Pending',
        'approved'  =>  'Approved',
        'rejected'  =>  'Rejected'
    ],
    'lang_path' => 'blog::blog_category.labels'
];

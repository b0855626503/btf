<?php

return [
    'deposit_default' => [
        'flow'      => 'three_step',
        'auto_post' => false,
        'roles'     => [], // ไม่ใช้แล้ว เมื่อมี ACL (คงไว้เพื่อ backward-compatible)
        'limits'    => ['min' => 1, 'max' => 200000],

        // ค่าเริ่มต้นของ permission key ต่อ action
        'permissions' => [
            'create'  => config('access.permissions.deposit.create',  'deposit.create'),
            'check'   => config('access.permissions.deposit.check',   'deposit.check'),
            'approve' => config('access.permissions.deposit.approve', 'deposit.approve'),
            'post'    => config('access.permissions.deposit.post',    'deposit.post.head'),
        ],
    ],
];

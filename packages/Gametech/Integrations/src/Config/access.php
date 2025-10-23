<?php

return [

    /*
    |--------------------------------------------------------------------------
    | ตาราง/คอลัมน์ของ ACL
    |--------------------------------------------------------------------------
    | ปรับชื่อได้ให้ตรงกับสคีมาจริงของโปรเจ็กต์คุณ
    */
    'tables' => [
        'roles'      => 'roles',
        'employees'  => 'employees',
    ],

    'columns' => [
        'roles' => [
            'id'               => 'code',             // ✅ ใช้ code แทน id
            'name'             => 'name',
            'permission_type'  => 'permission_type',
            'permissions'      => 'permissions',
        ],
        'employees' => [
            'id'                  => 'code',          // ✅ ใช้ code แทน id
            'role_id'             => 'role_id',
            'permissions_override'=> 'permissions_override',
            'level'               => 'level',
            'superadmin'          => 'superadmin',
        ],
    ],
    /*
    |--------------------------------------------------------------------------
    | Mapping ค่าเริ่มต้น: action -> permission key
    |--------------------------------------------------------------------------
    */
    'permissions' => [
        'deposit' => [
            'create'  => 'bank_in.refill',
            'check'   => 'bank_in.update',
            'approve' => 'bank_in.approve',
            'post'    => 'bank_in.approve',
        ],
    ],
    'integrations_config' => [
        'table' => 'configs',  // ชื่อตารางจริง
        'key'   => 'key',      // คอลัมน์คีย์
        'value' => 'value',    // คอลัมน์ค่า (เก็บ JSON/string)
        'ttl'   => 60,         // cache (วินาที)
    ],

    'cache_ttl'   => 120,
];

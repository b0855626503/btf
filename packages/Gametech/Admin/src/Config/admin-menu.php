<?php

return [
    [
        'key' => 'dashboard',
        'name' => 'DashBoard',
        'route' => 'admin.home.index',
        'sort' => 1,
        'icon-class' => 'fa-tachometer-alt',
        'badge' => 0,
        'badge-color' => 'badge-info',
        'status' => 1
    ], [
        'key' => 'bank_in',
        'name' => 'รายการ ฝาก',
        'route' => 'admin.bank_in.index',
        'sort' => 2,
        'icon-class' => 'fa-arrow-circle-left',
        'badge' => 1,
        'badge-color' => 'badge-warning',
        'status' => 1
    ], [
        'key' => 'withdraw',
        'name' => 'รายการ ถอนเงิน',
        'route' => 'admin.withdraw.index',
        'sort' => 3,
        'icon-class' => 'fa-wallet',
        'badge' => 1,
        'badge-color' => 'badge-warning',
        'status' => 1
    ], [
        'key' => 'member',
        'name' => 'รายการ สมาชิก',
        'route' => 'admin.member.index',
        'sort' => 4,
        'icon-class' => 'fa-users',
        'badge' => 0,
        'badge-color' => 'badge-primary',
        'status' => 1
    ], [
        'key' => 'ats',
        'name' => 'ตั้งค่าบัญชี',
        'route' => 'admin.bank_account_in.index',
        'sort' => 5,
        'icon-class' => 'fa-university',
        'badge' => 0,
        'badge-color' => 'badge-primary',
        'status' => 1
    ], [
        'key' => 'ats.bank_account_in',
        'name' => 'บัญชีรับเข้า',
        'route' => 'admin.bank_account_in.index',
        'sort' => 1,
        'icon-class' => '',
        'badge' => 0,
        'badge-color' => 'badge-primary',
        'status' => 1
    ], [
        'key' => 'ats.bank_account_out',
        'name' => 'บัญชีถอนออก',
        'route' => 'admin.bank_account_out.index',
        'sort' => 2,
        'icon-class' => '',
        'badge' => 0,
        'badge-color' => 'badge-primary',
        'status' => 1
    ], [
        'key' => 'promotion',
        'name' => 'เติมโปรโมชั่น',
        'route' => 'admin.promotion.index',
        'sort' => 6,
        'icon-class' => 'fa-gamepad',
        'badge' => 0,
        'badge-color' => 'badge-primary',
        'status' => 0
    ], [
        'key' => 'rp',
        'name' => 'ปวะวัติการดำเนินการ',
        'route' => 'admin.re.index',
        'sort' => 7,
        'icon-class' => 'fa-cog',
        'badge' => 0,
        'badge-color' => 'badge-primary',
        'status' => 1
    ], [
        'key' => 'rp.deposit',
        'name' => 'รายงานการฝาก',
        'route' => 'admin.rp_deposit.index',
        'sort' => 1,
        'icon-class' => 'fa-cog',
        'badge' => 0,
        'badge-color' => 'badge-primary',
        'status' => 1
    ], [
        'key' => 'rp.wihdraw',
        'name' => 'รายงานการถอน',
        'route' => 'admin.rp_withdraw.index',
        'sort' => 2,
        'icon-class' => 'fa-cog',
        'badge' => 0,
        'badge-color' => 'badge-primary',
        'status' => 1
    ], [
        'key' => 'rp.promotion',
        'name' => 'รายงานการติมโปร',
        'route' => 'admin.rp_promotion.index',
        'sort' => 3,
        'icon-class' => 'fa-cog',
        'badge' => 0,
        'badge-color' => 'badge-primary',
        'status' => 1
    ], [
        'key' => 'rp.refer',
        'name' => 'รายงาน แหล่งที่มา',
        'route' => 'admin.rp_refer.index',
        'sort' => 4,
        'icon-class' => 'fa-cog',
        'badge' => 0,
        'badge-color' => 'badge-primary',
        'status' => 1
    ], [
        'key' => 'st',
        'name' => 'ตั้งค่า ข้อมูลพื้นฐาน',
        'route' => 'admin.setting.index',
        'sort' => 8,
        'icon-class' => 'fa-cog',
        'badge' => 0,
        'badge-color' => 'badge-primary',
        'status' => 1
    ], [
        'key' => 'st.promotion',
        'name' => 'ข้อมูลโปรโมชั่น',
        'route' => 'admin.promotion.index',
        'sort' => 1,
        'icon-class' => '',
        'badge' => 0,
        'badge-color' => 'badge-primary',
        'status' => 1
    ], [
        'key' => 'st.bank',
        'name' => 'ข้อมูล ธนาคาร',
        'route' => 'admin.bank.index',
        'sort' => 2,
        'icon-class' => '',
        'badge' => 0,
        'badge-color' => 'badge-primary',
        'status' => 1
    ], [
        'key' => 'st.website',
        'name' => 'ข้อมูล Agent',
        'route' => 'admin.website.index',
        'sort' => 3,
        'icon-class' => '',
        'badge' => 0,
        'badge-color' => 'badge-primary',
        'status' => 1
    ], [
        'key' => 'dev',
        'name' => 'Admin Zone',
        'route' => 'admin.employees.index',
        'sort' => 9,
        'icon-class' => 'fa-cog',
        'badge' => 0,
        'badge-color' => 'badge-primary',
        'status' => 1
    ], [
        'key' => 'dev.employees',
        'name' => 'ผู้ใช้งานระบบ',
        'route' => 'admin.employees.index',
        'sort' => 1,
        'icon-class' => '',
        'badge' => 0,
        'badge-color' => 'badge-primary',
        'status' => 1
    ], [
        'key' => 'dev.roles',
        'name' => 'สิทธิ์ใช้งานระบบ',
        'route' => 'admin.roles.index',
        'sort' => 2,
        'icon-class' => '',
        'badge' => 0,
        'badge-color' => 'badge-primary',
        'status' => 1
    ], [
        'key' => 'dev.rp_log',
        'name' => 'Log',
        'route' => 'admin.rp_log.index',
        'sort' => 3,
        'icon-class' => '',
        'badge' => 0,
        'badge-color' => 'badge-primary',
        'status' => 1
    ]
];

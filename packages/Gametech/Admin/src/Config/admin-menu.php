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
        'sort' => 4,
        'icon-class' => 'fa-wallet',
        'badge' => 1,
        'badge-color' => 'badge-warning',
        'status' => 1
    ], [
        'key' => 'member',
        'name' => 'รายการ สมาชิก',
        'route' => 'admin.member.index',
        'sort' => 20,
        'icon-class' => 'fa-users',
        'badge' => 0,
        'badge-color' => 'badge-primary',
        'status' => 1
    ], [
        'key' => 'ats',
        'name' => 'ตั้งค่าบัญชี',
        'route' => 'admin.bank_account_in.index',
        'sort' => 70,
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
        'key' => 'top',
        'name' => 'เกมส์ & โปรโมชั่น',
        'route' => 'admin.game.index',
        'sort' => 80,
        'icon-class' => 'fa-gamepad',
        'badge' => 0,
        'badge-color' => 'badge-primary',
        'status' => 1
    ], [
        'key' => 'top.game',
        'name' => 'เกมส์',
        'route' => 'admin.game.index',
        'sort' => 1,
        'icon-class' => '',
        'badge' => 0,
        'badge-color' => 'badge-primary',
        'status' => 1
    ], [
        'key' => 'top.batch_user',
        'name' => 'Batch User',
        'route' => 'admin.batch_user.index',
        'sort' => 2,
        'icon-class' => '',
        'badge' => 0,
        'badge-color' => 'badge-primary',
        'status' => 1
    ], [
        'key' => 'top.promotion',
        'name' => 'โปรโมชั่น (ระบบ)',
        'route' => 'admin.promotion.index',
        'sort' => 3,
        'icon-class' => '',
        'badge' => 0,
        'badge-color' => 'badge-primary',
        'status' => 1
    ], [
        'key' => 'top.pro_content',
        'name' => 'โปรโมชั่น (เพิ่มเติม)',
        'route' => 'admin.pro_content.index',
        'sort' => 4,
        'icon-class' => '',
        'badge' => 0,
        'badge-color' => 'badge-primary',
        'status' => 1
    ], [
        'key' => 'st',
        'name' => 'ตั้งค่า ระบบ',
        'route' => 'admin.setting.index',
        'sort' => 90,
        'icon-class' => 'fa-cog',
        'badge' => 0,
        'badge-color' => 'badge-primary',
        'status' => 1
    ], [
        'key' => 'st.setting',
        'name' => 'ค่าพื้นฐานเว็บไซต์',
        'route' => 'admin.setting.index',
        'sort' => 1,
        'icon-class' => '',
        'badge' => 0,
        'badge-color' => 'badge-primary',
        'status' => 1
    ], [
        'key' => 'st.faq',
        'name' => 'คู่มือ',
        'route' => 'admin.faq.index',
        'sort' => 2,
        'icon-class' => '',
        'badge' => 0,
        'badge-color' => 'badge-primary',
        'status' => 1
    ], [
        'key' => 'st.refer',
        'name' => 'แหล่งที่มาการสมัคร',
        'route' => 'admin.refer.index',
        'sort' => 3,
        'icon-class' => '',
        'badge' => 0,
        'badge-color' => 'badge-primary',
        'status' => 1
    ], [
        'key' => 'st.bank',
        'name' => 'ธนาคาร',
        'route' => 'admin.bank.index',
        'sort' => 4,
        'icon-class' => '',
        'badge' => 0,
        'badge-color' => 'badge-primary',
        'status' => 1
    ], [
        'key' => 'st.spin',
        'name' => 'วงล้อมหาสนุก',
        'route' => 'admin.spin.index',
        'sort' => 5,
        'icon-class' => '',
        'badge' => 0,
        'badge-color' => 'badge-primary',
        'status' => 1
    ], [
        'key' => 'st.reward',
        'name' => 'ตั้งค่าของรางวัล',
        'route' => 'admin.reward.index',
        'sort' => 6,
        'icon-class' => '',
        'badge' => 0,
        'badge-color' => 'badge-primary',
        'status' => 1
    ], [
        'key' => 'st.notice',
        'name' => 'ตั้งค่าข้อความวิ่ง',
        'route' => 'admin.notice.index',
        'sort' => 7,
        'icon-class' => '',
        'badge' => 0,
        'badge-color' => 'badge-primary',
        'status' => 1
    ], [
        'key' => 'st.notice_new',
        'name' => 'ตั้งค่าประกาศ',
        'route' => 'admin.notice_new.index',
        'sort' => 8,
        'icon-class' => '',
        'badge' => 0,
        'badge-color' => 'badge-primary',
        'status' => 1
    ], [
        'key' => 'st.coupon',
        'name' => 'ตั้งค่าคูปอง',
        'route' => 'admin.coupon.index',
        'sort' => 9,
        'icon-class' => '',
        'badge' => 0,
        'badge-color' => 'badge-primary',
        'status' => 1
    ], [
        'key' => 'st.slide',
        'name' => 'ตั้งค่า สไลด์',
        'route' => 'admin.slide.index',
        'sort' => 9,
        'icon-class' => '',
        'badge' => 0,
        'badge-color' => 'badge-primary',
        'status' => 1
    ], [
        'key' => 'dev',
        'name' => 'Admin Zone',
        'route' => 'admin.employees.index',
        'sort' => 100,
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
        'key' => 'dev.rp_staff_log',
        'name' => 'Staff Activity Log',
        'route' => 'admin.rp_staff_log.index',
        'sort' => 3,
        'icon-class' => '',
        'badge' => 0,
        'badge-color' => 'badge-primary',
        'status' => 1
    ], [
        'key' => 'dev.rp_log',
        'name' => 'Log',
        'route' => 'admin.rp_log.index',
        'sort' => 4,
        'icon-class' => '',
        'badge' => 0,
        'badge-color' => 'badge-primary',
        'status' => 1
    ]
];

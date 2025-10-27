<?php

return [
    [
        'key' => 'dashboard',
        'name' => 'DashBoard',
        'route' => 'admin.home.index',
        'sort' => 1
    ], [
        'key' => 'dashboard.deposit',
        'name' => 'สิทธิ์ เห็นข้อมูลยอดฝาก',
        'route' => '',
        'sort' => 1
    ], [
        'key' => 'dashboard.withdraw',
        'name' => 'สิทธิ์ เห็นข้อมูลยอดถอน',
        'route' => '',
        'sort' => 2
    ], [
        'key' => 'dashboard.bonus',
        'name' => 'สิทธิ์ เห็นข้อมูลยอดโบนัส',
        'route' => '',
        'sort' => 3
    ], [
        'key' => 'dashboard.balance',
        'name' => 'สิทธิ์ เห็นข้อมูลยอดคงเหลือ',
        'route' => '',
        'sort' => 4
    ], [
        'key' => 'dashboard.deposit_wait',
        'name' => 'สิทธิ์ เห็นข้อมูลยอดฝาก (มีปัญหา)',
        'route' => '',
        'sort' => 5
    ], [
        'key' => 'dashboard.setdeposit',
        'name' => 'สิทธิ์ เห็นข้อมูลทีมงานเพิ่ม ยอดเงิน',
        'route' => '',
        'sort' => 6
    ], [
        'key' => 'dashboard.setwithdraw',
        'name' => 'สิทธิ์ เห็นข้อมูลทีมงานลด ยอดเงิน',
        'route' => '',
        'sort' => 7
    ], [
        'key' => 'dashboard.income',
        'name' => 'สิทธิ์ เห็นข้อมูลรายได้',
        'route' => '',
        'sort' => 8
    ], [
        'key' => 'dashboard.topup',
        'name' => 'สิทธิ์ เห็นข้อมูลเติมเงิน',
        'route' => '',
        'sort' => 9
    ], [
        'key' => 'dashboard.regis',
        'name' => 'สิทธิ์ เห็นข้อมูลสมาชิกใหม่',
        'route' => '',
        'sort' => 10
    ], [
        'key' => 'dashboard.bankin',
        'name' => 'สิทธิ์ เห็นข้อมูลบัญชีเงินเข้า',
        'route' => '',
        'sort' => 11
    ], [
        'key' => 'dashboard.bankout',
        'name' => 'สิทธิ์ เห็นข้อมูลบัญชีเงินออก',
        'route' => '',
        'sort' => 12
    ], [
        'key' => 'bank_in',
        'name' => 'รายการ ฝาก',
        'route' => 'admin.bank_in.index',
        'sort' => 2
    ], [
        'key' => 'bank_in.refill',
        'name' => 'สิทธิ์ เพิ่ม รายการฝาก',
        'route' => 'admin.bank_in.refill',
        'sort' => 1
    ], [
        'key' => 'bank_in.update',
        'name' => 'สิทธิ์ ยืนยัน รายการฝาก',
        'route' => 'admin.bank_in.update',
        'sort' => 2
    ], [
        'key' => 'bank_in.cancel',
        'name' => 'สิทธิ์ ยกเลิกยืนยัน รายการฝาก',
        'route' => 'admin.bank_in.cancel',
        'sort' => 3
    ], [
        'key' => 'bank_in.approve',
        'name' => 'สิทธิ์ อนุมัติ/เติม รายการฝาก',
        'route' => 'admin.bank_in.approve',
        'sort' => 4
    ], [
        'key' => 'bank_in.delete',
        'name' => 'สิทธิ์ ลบ รายการฝาห',
        'route' => 'admin.bank_in.delete',
        'sort' => 5
    ], [
        'key' => 'withdraw',
        'name' => 'รายการ แจ้งถอน',
        'route' => 'admin.withdraw.index',
        'sort' => 3
    ], [
        'key' => 'withdraw.create',
        'name' => 'สิทธิ์ สร้าง รายการแจ้งถอน',
        'route' => 'admin.withdraw.create',
        'sort' => 1
    ], [
        'key' => 'withdraw.update',
        'name' => 'สิทธิ์ ตัดยอด รายการแจ้งถอน',
        'route' => 'admin.withdraw.update',
        'sort' => 2
    ], [
        'key' => 'withdraw.approve',
        'name' => 'สิทธิ์ อนุมัติ รายการแจ้งถอน',
        'route' => 'admin.withdraw.update',
        'sort' => 3
    ], [
        'key' => 'withdraw.delete',
        'name' => 'สิทธิ์ ลบ รายการถอน',
        'route' => 'admin.withdraw.delete',
        'sort' => 4
    ], [
        'key' => 'member',
        'name' => 'ข้อมูลสมาชิก',
        'route' => 'admin.member.index',
        'sort' => 4
    ], [
        'key' => 'member.index',
        'name' => 'สิทธิ์เข้า เมนู ข้อมูลสมาชิก',
        'route' => 'admin.member.index',
        'sort' => 1
    ], [
        'key' => 'member.create',
        'name' => 'สิทธิ์ เพิ่ม ข้อมูลสมาชิก',
        'route' => 'admin.member.create',
        'sort' => 2
    ], [
        'key' => 'member.update',
        'name' => 'สิทธิ์ แก้ไข ข้อมูลสมาชิก',
        'route' => 'admin.member.update',
        'sort' => 3
    ], [
        'key' => 'member.delete',
        'name' => 'สิทธิ์ ลบ ข้อมูลสมาชิก',
        'route' => 'admin.member.delete',
        'sort' => 4
    ], [
        'key' => 'ats',
        'name' => 'ตั้งค่าบัญชี',
        'route' => 'admin.bank_account_in.index',
        'sort' => 5
    ], [
        'key' => 'ats.bank_account_in',
        'name' => 'บัญชีรับเข้า',
        'route' => 'admin.bank_account_in.index',
        'sort' => 1
    ], [
        'key' => 'ats.bank_account_in.index',
        'name' => 'สิทธิ์เข้า เมนู บัญชีรับเข้า',
        'route' => 'admin.bank_account_in.index',
        'sort' => 1
    ], [
        'key' => 'ats.bank_account_in.create',
        'name' => 'สิทธิ์ เพิ่มบัญชีรับเข้า',
        'route' => 'admin.bank_account_in.create',
        'sort' => 2,
    ], [
        'key' => 'ats.bank_account_in.update',
        'name' => 'สิทธิ์ แก้ไขบัญชีรับเข้า',
        'route' => 'admin.bank_account_in.update',
        'sort' => 3,
    ], [
        'key' => 'ats.bank_account_in.delete',
        'name' => 'สิทธิ์ ลบบัญชีรับเข้า',
        'route' => 'admin.bank_account_in.delete',
        'sort' => 4,
    ], [
        'key' => 'ats.bank_account_out',
        'name' => 'บัญชีถอนออก',
        'route' => 'admin.bank_account_out.index',
        'sort' => 2
    ], [
        'key' => 'ats.bank_account_out.index',
        'name' => 'สิทธิ์เข้า เมนู บัญชี ถอน',
        'route' => 'admin.bank_account_out.index',
        'sort' => 1
    ], [
        'key' => 'ats.bank_account_out.create',
        'name' => 'สิทธิ์ เพิ่มบัญชีถอนออก',
        'route' => 'admin.bank_account_out.create',
        'sort' => 2,
    ], [
        'key' => 'ats.bank_account_out.update',
        'name' => 'สิทธิ์ แก้ไขบัญชีถอนออก',
        'route' => 'admin.bank_account_out.update',
        'sort' => 3,
    ], [
        'key' => 'ats.bank_account_out.delete',
        'name' => 'สิทธิ์ ลบบัญชีถอนออก',
        'route' => 'admin.bank_account_out.delete',
        'sort' => 4,
    ], [
        'key' => 'top',
        'name' => 'เกมส์ & โปรโมชั่น',
        'route' => 'admin.game.index',
        'sort' => 80
    ], [
        'key' => 'top.game',
        'name' => 'เกมส์',
        'route' => 'admin.game.index',
        'sort' => 1
    ], [
        'key' => 'top.game.update',
        'name' => 'แก้ไขเกมส์',
        'route' => 'admin.game.update',
        'sort' => 1,

    ], [
        'key' => 'top.batch_user',
        'name' => 'Batch User',
        'route' => 'admin.batch_user.index',
        'sort' => 2
    ], [
        'key' => 'top.batch_user.create',
        'name' => 'เพิ่ม Batch User',
        'route' => 'admin.batch_user.create',
        'sort' => 1,
    ], [
        'key' => 'top.promotion',
        'name' => 'โปรโมชั่น (ระบบ)',
        'route' => 'admin.promotion.index',
        'sort' => 3
    ], [
        'key' => 'top.promotion.update',
        'name' => 'แก้ไข โปรโมชั่น (ระบบ)',
        'route' => 'admin.promotion.update',
        'sort' => 1,
    ], [
        'key' => 'top.pro_content',
        'name' => 'โปรโมชั่น (เพิ่มเติม)',
        'route' => 'admin.pro_content.index',
        'sort' => 4
    ], [
        'key' => 'top.pro_content.create',
        'name' => 'เพิ่ม โปรโมชั่น (เพิ่มเติม)',
        'route' => 'admin.pro_content.create',
        'sort' => 1,
    ], [
        'key' => 'top.pro_content.update',
        'name' => 'แก้ไข โปรโมชั่น (เพิ่มเติม)',
        'route' => 'admin.pro_content.update',
        'sort' => 2,
    ], [
        'key' => 'top.pro_content.delete',
        'name' => 'ลบ โปรโมชั่น (เพิ่มเติม)',
        'route' => 'admin.pro_content.delete',
        'sort' => 3,
    ], [
        'key' => 'st',
        'name' => 'ตั้งค่า ระบบ',
        'route' => 'admin.setting.index',
        'sort' => 90
    ], [
        'key' => 'st.setting',
        'name' => 'ค่าพื้นฐานเว็บไซต์',
        'route' => 'admin.setting.index',
        'sort' => 1
    ], [
        'key' => 'st.setting.update',
        'name' => 'แก้ไข ค่าพื้นฐานเว็บไซต์',
        'route' => 'admin.setting.update',
        'sort' => 1
    ], [
        'key' => 'st.faq',
        'name' => 'คู่มือ',
        'route' => 'admin.faq.index',
        'sort' => 2
    ], [
        'key' => 'st.faq.create',
        'name' => 'เพิ่ม คู่มือ',
        'route' => 'admin.faq.create',
        'sort' => 1
    ], [
        'key' => 'st.faq.update',
        'name' => 'แก้ไข คู่มือ',
        'route' => 'admin.faq.update',
        'sort' => 2
    ], [
        'key' => 'st.faq.delete',
        'name' => 'ลบ คู่มือ',
        'route' => 'admin.faq.delete',
        'sort' => 3
    ], [
        'key' => 'st.refer',
        'name' => 'แหล่งที่มาการสมัคร',
        'route' => 'admin.refer.index',
        'sort' => 3
    ], [
        'key' => 'st.refer.update',
        'name' => 'แก้ไข แหล่งที่มาการสมัคร',
        'route' => 'admin.refer.update',
        'sort' => 1
    ], [
        'key' => 'st.bank',
        'name' => 'ธนาคาร',
        'route' => 'admin.bank.index',
        'sort' => 4
    ], [
        'key' => 'st.bank.update',
        'name' => 'แก้ไข ธนาคาร',
        'route' => 'admin.bank.update',
        'sort' => 1
    ], [
        'key' => 'st.bank_rule',
        'name' => 'การมองเห็นธนาคาร',
        'route' => 'admin.bank_rule.index',
        'sort' => 5
    ], [
        'key' => 'st.bank_rule.create',
        'name' => 'เพิ่ม การมองเห็นธนาคาร',
        'route' => 'admin.bank_rule.create',
        'sort' => 1
    ], [
        'key' => 'st.bank_rule.update',
        'name' => 'แก้ไข การมองเห็นธนาคาร',
        'route' => 'admin.bank_rule.update',
        'sort' => 2
    ], [
        'key' => 'st.bank_rule.delete',
        'name' => 'ลบ การมองเห็นธนาคาร',
        'route' => 'admin.bank_rule.delete',
        'sort' => 3
    ], [
        'key' => 'st.spin',
        'name' => 'วงล้อมหาสนุก',
        'route' => 'admin.spin.index',
        'sort' => 6
    ], [
        'key' => 'st.spin.update',
        'name' => 'แก้ไข วงล้อมหาสนุก',
        'route' => 'admin.spin.update',
        'sort' => 1
    ], [
        'key' => 'st.reward',
        'name' => 'ตั้งค่าของรางวัล',
        'route' => 'admin.reward.index',
        'sort' => 7
    ], [
        'key' => 'st.reward.create',
        'name' => 'เพิ่ม ตั้งค่าของรางวัล',
        'route' => 'admin.reward.create',
        'sort' => 1
    ], [
        'key' => 'st.reward.update',
        'name' => 'แก้ไข ตั้งค่าของรางวัล',
        'route' => 'admin.reward.update',
        'sort' => 2
    ], [
        'key' => 'st.reward.delete',
        'name' => 'ลบ ตั้งค่าของรางวัล',
        'route' => 'admin.reward.delete',
        'sort' => 3
    ], [
        'key' => 'st.notice',
        'name' => 'ตั้งค่าประกาศ',
        'route' => 'admin.notice.index',
        'sort' => 8
    ], [
        'key' => 'st.notice.create',
        'name' => 'เพิ่ม ประกาศ',
        'route' => 'admin.notice.create',
        'sort' => 1
    ], [
        'key' => 'st.notice.update',
        'name' => 'แก้ไข ประกาศ',
        'route' => 'admin.notice.update',
        'sort' => 2
    ], [
        'key' => 'st.notice.delete',
        'name' => 'ลบ ประกาศ',
        'route' => 'admin.notice.delete',
        'sort' => 3
    ], [
        'key' => 'dev',
        'name' => 'Admin Zone',
        'route' => 'admin.employees.index',
        'sort' => 100
    ], [
        'key' => 'dev.employees',
        'name' => 'ผู้ใช้งานระบบ',
        'route' => 'admin.employees.index',
        'sort' => 1
    ], [
        'key' => 'dev.employees.create',
        'name' => 'เพิ่ม ผู้ใช้งานระบบ',
        'route' => 'admin.employees.create',
        'sort' => 1
    ], [
        'key' => 'dev.employees.update',
        'name' => 'แก้ไข ผู้ใช้งานระบบ',
        'route' => 'admin.employees.update',
        'sort' => 2
    ], [
        'key' => 'dev.employees.delete',
        'name' => 'ลบ ผู้ใช้งานระบบ',
        'route' => 'admin.employees.delete',
        'sort' => 3
    ], [
        'key' => 'dev.roles',
        'name' => 'สิทธิ์ ใช้งานระบบ',
        'route' => 'admin.roles.index',
        'sort' => 2
    ], [
        'key' => 'dev.roles.create',
        'name' => 'เพิ่ม สิทธิ์ ใช้งานระบบ',
        'route' => 'admin.roles.create',
        'sort' => 1
    ], [
        'key' => 'dev.roles.update',
        'name' => 'แก้ไข สิทธิ์ ใช้งานระบบ',
        'route' => 'admin.roles.update',
        'sort' => 2
    ], [
        'key' => 'dev.roles.delete',
        'name' => 'ลบ สิทธิ์ ใช้งานระบบ',
        'route' => 'admin.roles.delete',
        'sort' => 3
    ], [
        'key' => 'dev.rp_staff_log',
        'name' => 'Staff Activity Log',
        'route' => 'admin.rp_staff_log.index',
        'sort' => 3
    ], [
        'key' => 'dev.rp_log',
        'name' => 'Log',
        'route' => 'admin.r_log.index',
        'sort' => 4
    ]
];

<?php

namespace Gametech\TelegramBot\Console\Commands;

use Gametech\TelegramBot\Models\BroadcastSessionProxy;
use Gametech\TelegramBot\Models\TelegramAdminProxy;
use Telegram\Bot\Commands\Command;

class WelcomeCommand extends Command
{
    protected $name = 'welcome';

    protected $description = 'ตั้งค่าข้อความ ต้อนรับ เมื่อมีคน คุยกับบอท แบบส่วนตัว';

    public function handle()
    {
        $userId = $this->getUpdate()->getMessage()->getFrom()->getId();
        $admins = TelegramAdminProxy::pluck('user_id')->toArray();
        if (! in_array($userId, $admins)) {
            $this->replyWithMessage([
                'text' => '⛔ คุณไม่มีสิทธิ์ใช้งานคำสั่งนี้',
            ]);

            return;
        }

        // Step 1: set session
        BroadcastSessionProxy::updateOrCreate(['user_id' => $userId]);
        $this->replyWithMessage([
            'text' => "โปรดส่งข้อความที่ต้องการตั้งเป็นข้อความต้อนรับ หรือแนบรูป/ไฟล์ด้วย (ถ้าต้องการ)\n\nถ้าต้องการแนบลิงก์ไว้ใต้ประกาศ ให้พิมพ์ไว้ในข้อความ เช่น\n\nข้อความหลัก\n\nลิงก์: https://yourlink.com",
        ]);
    }
}

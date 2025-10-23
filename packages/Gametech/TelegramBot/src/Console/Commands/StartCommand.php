<?php

namespace Gametech\TelegramBot\Console\Commands;

use Gametech\TelegramBot\Models\TelegramAdminProxy;
use Gametech\TelegramBot\Models\TelegramCustomerMenuProxy;
use Telegram\Bot\Commands\Command;

class StartCommand extends Command
{
    protected $name = 'start';

    protected $description = 'เริ่มใช้งานบอท';

    public function handle()
    {
        $userId = $this->getUpdate()->getMessage()->getFrom()->getId();

        // ดึง user id แอดมินจาก DB หรือ Proxy
        $admins = TelegramAdminProxy::pluck('user_id')->toArray();

        if (in_array($userId, $admins)) {
            $keyboard = [
                [
                    ['text' => '📢 ส่งประกาศ', 'callback_data' => 'broadcast'],
                    ['text' => '📢 ตั้งข้อความต้อนรับ', 'callback_data' => 'welcome'],
                ],
            ];
            $text = '🔒 เมนูสำหรับแอดมิน';
        } else {

            $menus = TelegramCustomerMenuProxy::where('active', 1)
                ->orderBy('position')
                ->get();

            $keyboard = [];
            foreach ($menus->chunk(2) as $chunk) { // แถวละ 2 ปุ่ม
                $row = [];
                foreach ($chunk as $menu) {
                    if ($menu->type == 'url') {
                        $row[] = ['text' => $menu->title, 'url' => $menu->value];
                    } elseif ($menu->type == 'callback') {
                        $row[] = ['text' => $menu->title, 'callback_data' => $menu->value];
                    }
                    // ต่อเติม type อื่นได้
                }
                $keyboard[] = $row;
            }

            $text = 'เมนูสำหรับสมาชิกทั่วไป';
        }

        $this->replyWithMessage([
            'text' => $text,
            'reply_markup' => json_encode([
                'inline_keyboard' => $keyboard,
            ]),
        ]);
    }
}

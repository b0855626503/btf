<?php

namespace Gametech\TelegramBot\Console\Commands;

use Gametech\TelegramBot\Models\TelegramAdminProxy;
use Gametech\TelegramBot\Models\TelegramConfigProxy;
use Telegram\Bot\Commands\Command;

class RegisterCommand extends Command
{
    protected $name = 'register';

    protected $description = 'ลงทะเบียนใช้งานบอท ในกลุ่มที่ต้องการ';

    public function handle()
    {
        $message = $this->getUpdate()->getMessage();
        $text = trim($message->getText());
        $parts = preg_split('/\s+/', $text);

        // รูปแบบที่ต้องการ: /register 123456
        if (count($parts) != 2) {
            return $this->replyWithMessage([
                'text' => "โปรดกรอกแบบนี้: /register รหัส6หลัก เช่น\n/register 123456",
            ]);
        }

        $code = $parts[1];

        // ตรวจสอบรหัสเป็นตัวเลข 6 หลัก
        if (! preg_match('/^\d{6}$/', $code)) {
            return $this->replyWithMessage([
                'text' => 'รหัสไม่ถูกต้อง! ต้องเป็นตัวเลข 6 หลักเท่านั้น เช่น 123456',
            ]);
        }

        $config = TelegramConfigProxy::where('register_code', $code)->first();
        if (! $config) {
            return $this->replyWithMessage([
                'text' => '❌ รหัสไม่ถูกต้อง กรุณาตรวจสอบใหม่',
            ]);

        }

        $chat = $this->getUpdate()->getMessage()->getChat();
        $chatId = $chat->getId();
        $chatType = $chat->getType(); // group, supergroup, channel, private

        // ตรวจสอบรหัส

        // ตรวจสอบชนิด chat
        if ($chatType === 'channel' || $chatType === 'supergroup' || $chatType === 'group') {
            // บันทึกข้อมูล channel/group ลง DB
            $config->channel_chat_id = $chatId;
            $config->channel_title = $chat->getTitle() ?? '';
            $config->channel_type = $chatType;
            $config->channel_registered_at = now();
            $config->save();

            $this->replyWithMessage([
                'text' => "✅ ลงทะเบียน Channel/Group สำเร็จ!\nChat ID: {$chatId}",
            ]);
        } else {

            $user = $this->getUpdate()->getMessage()->getFrom();
            $admin = TelegramAdminProxy::firstOrNew([
                'user_id' => $user->getId(),
            ]);

            $admin->first_name = $user->getFirstName() ?? '';
            $admin->last_name = $user->getLastName() ?? '';
            $admin->username = $user->getUsername() ?? '';
            $admin->registered_at = now();
            $admin->save();

            $this->replyWithMessage([
                'text' => "✅ ลงทะเบียนผู้ดูแลระบบสำเร็จ!\nUser ID: {$user->getId()}",
            ]);
        }
    }
}

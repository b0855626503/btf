<?php

namespace Gametech\TelegramBot\Http\Controllers;

use App\Http\Controllers\Controller;
use Gametech\TelegramBot\Console\Commands\BroadcastCommand;
use Gametech\TelegramBot\Console\Commands\RegisterCommand;
use Gametech\TelegramBot\Console\Commands\StartCommand;
use Gametech\TelegramBot\Console\Commands\WelcomeCommand;
use Gametech\TelegramBot\Models\BroadcastSessionProxy as BroadcastSession;
use Gametech\TelegramBot\Models\TelegramAdminProxy;
use Gametech\TelegramBot\Models\TelegramConfigProxy;
use Gametech\TelegramBot\Models\TelegramWelcomeMessageProxy;
use Illuminate\Http\Request;
use Telegram\Bot\Api;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $config = TelegramConfigProxy::first();
        if (! $config || empty($config->bot_token)) {
            return response()->json(['ok' => false, 'error' => 'Token not found'], 400);
        }
        $token = $config->bot_token;
        $telegram = new Api($token);

        $telegram->addCommands([
            StartCommand::class,
            RegisterCommand::class,
            BroadcastCommand::class,
            WelcomeCommand::class,
        ]);

        $update = $telegram->commandsHandler(true);

        // ---------- รองรับ callback_query หลายแบบ ----------
        if (isset($update['callback_query'])) {
            $callback = $update['callback_query'];
            $chatId = $callback['message']['chat']['id'];
            $userId = $callback['from']['id'];
            $data = $callback['data']; // callback_data จากปุ่ม

            // 1. ตรวจสอบสิทธิ์ admin
            $isAdmin = TelegramAdminProxy::where('user_id', $userId)->exists();

            switch ($data) {
                // ----- ADMIN MENU -----
                case 'broadcast':
                    if ($isAdmin) {
                        // สร้าง session (หรือ update ถ้ามี)
                        BroadcastSession::updateOrCreate(['user_id' => $userId]);
                        // แจ้ง admin ให้ส่งข้อความที่จะประกาศ
                        $telegram->sendMessage([
                            'chat_id' => $userId,
                            'text' => "โปรดพิมพ์ข้อความ หรือส่งรูป/ไฟล์ที่ต้องการประกาศ\n(คุณสามารถแนบลิงก์ได้ด้วย เช่น 'ลิงก์: https://...')",
                        ]);
                        return response()->json(['ok' => true]);
                    }
                    // ไม่ใช่ admin ห้ามใช้
                    $telegram->sendMessage([
                        'chat_id' => $userId,
                        'text' => $userId.'❌ คุณไม่มีสิทธิ์ใช้คำสั่งนี้ ขออภัย '.$isAdmin,
                    ]);
                    return response()->json(['ok' => true]);
                    break;

                case 'welcome':
                    if ($isAdmin) {
                        BroadcastSession::updateOrCreate(['user_id' => $userId]);
                        $telegram->sendMessage([
                            'chat_id' => $userId,
                            'text' => "โปรดพิมพ์ข้อความ หรือส่งรูป/ไฟล์ที่ต้องการตั้งเป็นข้อความต้อนรับ\n(คุณสามารถแนบลิงก์ได้ด้วย เช่น 'ลิงก์: https://...')",
                        ]);
                        return response()->json(['ok' => true]);
                    }
                    $telegram->sendMessage([
                        'chat_id' => $userId,
                        'text' => $userId.'❌ คุณไม่มีสิทธิ์ใช้คำสั่งนี้ ขออภัย '.$isAdmin,
                    ]);
                    return response()->json(['ok' => true]);
                    break;

                // ----- CLIENT MENU -----
                case 'client_website':
                    $telegram->sendMessage([
                        'chat_id' => $userId,
                        'text' => 'กดลิงก์เพื่อเข้าสู่เว็บไซต์: https://yourwebsite.com',
                    ]);
                    return response()->json(['ok' => true]);
                    break;

                case 'client_register':
                    $telegram->sendMessage([
                        'chat_id' => $userId,
                        'text' => 'ลงทะเบียนได้ที่นี่: https://yourwebsite.com/register',
                    ]);
                    return response()->json(['ok' => true]);
                    break;

                // ----- เพิ่ม callback กรณีอื่น ๆ ได้ที่นี่ -----
                case 'client_help':
                    $telegram->sendMessage([
                        'chat_id' => $userId,
                        'text' => 'สอบถามข้อมูลเพิ่มเติม ติดต่อ admin',
                    ]);
                    return response()->json(['ok' => true]);
                    break;

                default:
                    $telegram->sendMessage([
                        'chat_id' => $userId,
                        'text' => '❓ เมนูไม่รองรับหรือหมดอายุ กรุณาลองใหม่',
                    ]);
                    return response()->json(['ok' => true]);
                    break;
            }
        }

        // ------- สำหรับข้อความธรรมดาหรือ command (message) ------
        if (isset($update['message'])) {
            $message = $update['message'];
            $userId = $message['from']['id'];

            // === 1. เช็กว่ากำลังอยู่ใน session broadcast หรือ welcome ไหม ===
            if (BroadcastSession::where('user_id', $userId)->exists()) {
                // --- สมมุติให้แยกจากกันด้วยข้อความ ---
                // (จริงๆ อาจแยกด้วย session_type ก็ได้)
                if ($this->isWelcomeSession($userId)) {
                    $this->handleWelcomeMessage($telegram, $config, $message, $userId);
                } else {
                    $this->handleBroadcastSession($telegram, $config, $message, $userId);
                }
                return response()->json(['ok' => true]);
            }
        }

        return response()->json(['ok' => true]);
    }

    protected function handleBroadcastSession(Api $telegram, $config, $message, $userId)
    {
        $caption = '';
        $link = '';

        $text = $message['text'] ?? '';
        // ถอด link ถ้ามี
//        if (preg_match('/^(.*)(ลิงก์:|link:)\s*(https?:\/\/[^\s]+)$/imu', $text, $matches)) {
//            $caption = trim($matches[1]);
//            $link = trim($matches[3]);
//        } else {
//            $caption = $text;
//        }
        $caption = $text;

        $announce = $caption;
        if ($link) {
            $announce .= "\n\n🔗 <a href=\"{$link}\">{$link}</a>";
        }

        $sent = false;
        if (! empty($message['photo'])) {
            $photo = end($message['photo']);
            $fileId = $photo['file_id'];
            $telegram->sendPhoto([
                'chat_id' => $config->channel_chat_id,
                'photo' => $fileId,
                'caption' => $announce,
            ]);
            $sent = true;
        } elseif (! empty($message['document'])) {
            $fileId = $message['document']['file_id'];
            $telegram->sendDocument([
                'chat_id' => $config->channel_chat_id,
                'document' => $fileId,
                'caption' => $announce,
            ]);
            $sent = true;
        } elseif (! empty($message['animation'])) {
            $fileId = $message['animation']['file_id'];
            $telegram->sendAnimation([
                'chat_id' => $config->channel_chat_id,
                'animation' => $fileId,
                'caption' => $announce,
            ]);
            $sent = true;
        } elseif (! empty($announce)) {
            $telegram->sendMessage([
                'chat_id' => $config->channel_chat_id,
                'text' => $announce,
            ]);
            $sent = true;
        }

        // แจ้งกลับ
        if ($sent) {
            $telegram->sendMessage([
                'chat_id' => $userId,
                'text' => '✅ ประกาศเรียบร้อย',
            ]);
        } else {
            $telegram->sendMessage([
                'chat_id' => $userId,
                'text' => '❗️ประกาศไม่สำเร็จ กรุณาส่งข้อความหรือรูป/ไฟล์ใหม่',
            ]);
        }

        // clear session
        BroadcastSession::where('user_id', $userId)->delete();
    }

    protected function handleWelcomeMessage(Api $telegram, $config, $message, $userId)
    {
        $caption = '';
        $link = '';
        $fileId = null;
        $mediaType = null;
        $text = $message['text'] ?? '';

        // ถอด link ถ้ามี
        if (preg_match('/^(.*)(ลิงก์:|link:)\s*(https?:\/\/[^\s]+)$/imu', $text, $matches)) {
            $caption = trim($matches[1]);
            $link = trim($matches[3]);
        } else {
            $caption = $text;
        }

        $announce = $caption;
        if ($link) {
            $announce .= "\n\n🔗 <a href=\"{$link}\">{$link}</a>";
        }

        if (! empty($message['photo'])) {
            $photo = end($message['photo']);
            $fileId = $photo['file_id'];
            $mediaType = 'photo';
        } elseif (! empty($message['document'])) {
            $fileId = $message['document']['file_id'];
            $mediaType = 'document';
        } elseif (! empty($message['animation'])) {
            $fileId = $message['animation']['file_id'];
            $mediaType = 'animation';
        } else {
            $fileId = null;
            $mediaType = null;
        }

        // ป้องกันการบันทึกเปล่า
        if (empty($caption) && empty($fileId)) {
            $telegram->sendMessage([
                'chat_id' => $userId,
                'text' => 'กรุณาส่งข้อความ หรือ รูปภาพ/ไฟล์ อย่างน้อย 1 อย่าง เพื่อบันทึกข้อความต้อนรับ',
            ]);

            return;
        }

        $response = TelegramWelcomeMessageProxy::updateOrCreate(['id' => 1],
            [
                'message' => $caption,
                'media_url' => $fileId,   // ส่ง path/media id ที่เก็บไว้
                'media_type' => $mediaType,  // photo/video/animation/document
                'lang' => 'th',        // ถ้ามี multi-lang หรือ 'th' default
            ]);

        if ($response) {
            $telegram->sendMessage([
                'chat_id' => $userId,
                'text' => '✅ บันทึก การตั้งค่าข้อความต้อนรับ เรียบร้อยแล้ว',
            ]);
        }

        // clear session
        BroadcastSession::where('user_id', $userId)->delete();
    }

    // เพิ่มฟังก์ชันนี้ถ้าอยากแยก session ระหว่าง broadcast กับ welcome
    protected function isWelcomeSession($userId)
    {
        // ตัวอย่างแบบ basic:
        // return true ถ้า session ถูกสร้างจากปุ่ม welcome
        // return false ถ้าจากปุ่ม broadcast
        // (ถ้าอยากแยกจริงๆ ให้เพิ่ม field type ลงใน BroadcastSessionProxy)
        return false;
    }
}

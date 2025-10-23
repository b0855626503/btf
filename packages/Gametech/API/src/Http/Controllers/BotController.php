<?php

namespace Gametech\API\Http\Controllers;

use Gametech\API\Models\Violation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Telegram\Bot\Api;
use Telegram\Bot\FileUpload\InputFile;

class BotController extends AppBaseController
{
    protected $telegram;

    public function __construct(Api $telegram)
    {
        $this->telegram = $telegram;
    }

    /**
     * Show the bot information.
     */
    public function show()
    {
        $response = $this->telegram->setConnectTimeOut(110)->getMe();

        return $response;
    }

    public function mentionUser(int $userId, string $name): string
    {
        return "[{$name}](tg://user?id={$userId})";
    }

    public function handleMessage(Request $request)
    {
        try {
            $update = $this->telegram->setTimeOut(10)->setConnectTimeOut(10)->commandsHandler(true);
//            \Log::info('Telegram Update:', $update->toArray()); // บันทึกข้อมูล update
            
            if ($update->isType('message')) {
                $path = storage_path('logs/bot/webhook_'.now()->format('Y_m_d').'.log');
                file_put_contents($path, print_r($update, true), FILE_APPEND);

                $message = $update->getMessage();

                $chatId = $message->getChat()->getId();
                $text = $message->getText();
                $userId = $message->getFrom()->getId();

                $currentStep = Violation::where('user_id', $userId)->latest()->first();

                $confirmation = strtolower($text);
                $validResponses = ['/report', 'แจ้งคนโกง', 'มิจฉาชีพ'];
                if (in_array($confirmation, $validResponses)) {
                    if (! $currentStep || $currentStep->step == 'completed') {
                        $newSchedule = new Violation;
                        $newSchedule->user_id = $userId;
                        $newSchedule->step = 'media';
                        $newSchedule->save();
                        $this->telegram->setTimeOut(10)->setConnectTimeOut(10)->sendMessage([
                            'chat_id' => $chatId,
                            'text' => 'ส่งข้อมูลมาได้เลยคับ แนบรูปมาด้วยก็ได้ถ้ามี',
                        ]);
                    }
                } elseif ($text == 'ยกเลิก') {
                    $this->telegram->setTimeOut(10)->setConnectTimeOut(10)->sendMessage([
                        'chat_id' => $chatId,
                        'text' => 'อะเค ไม่ถามต่อแล้ว',
                    ]);

                } else {
                    if ($message->has('new_chat_members')) {
                        foreach ($message->getNewChatMembers() as $newMember) {
                            $firstName = $newMember->getFirstName() ?? '';
                            $lastName = $newMember->getLastName() ?? '';
                            $username = $newMember->getUsername() ?? '';
                            $userId = $newMember->getId() ?? '';
                            $fullname = trim("$firstName $lastName");

                            if($username === ''){
                                $username = $this->mentionUser($userId, $firstName);
                            }else{
                                $username = '@'.$username;
                            }

                            try {
                                $this->telegram->setTimeOut(10)->setConnectTimeOut(10)->sendMessage([
                                    'chat_id' => $chatId,
                                    'text' => "สวัสดีคับ $fullname ($username)\n ต้องการแจ้งคนโกง ติดต่อ Admin ได้เลย\n อยากค้นหาใคร พิมพ์ ชื่อ นามสกุล ได้เลย",
                                ]);
                            } catch (\Telegram\Bot\Exceptions\TelegramResponseException $e) {
                                \Log::error('Telegram API Error: '.$e->getMessage());
                                \Log::error('Chat ID: '.$chatId);
                                \Log::error('New Member: '.json_encode($newMember));
                            }
                        }
                    }

                    if (! $currentStep || $currentStep->step == 'completed') {
                        $text = preg_replace('/[^\p{Thai}\p{L}\p{Nd} ]+/u', '', $text);
                        $results = $this->searchViolations($text);
                        if (count($results) > 0) {

                            foreach ($results as $i => $result) {
                                $n = $i + 1;

                                if ($result->media_url) {

                                    $sendphoto = InputFile::create($result->media_url, 'image.jpg');
                                    $this->telegram->setTimeOut(10)->setConnectTimeOut(10)->sendPhoto([
                                        'chat_id' => $chatId,
                                        'photo' => $sendphoto,
                                        'caption' => $result->message,
                                    ]);

                                } else {
                                    $media = 'https://user.168csn.com/images/alert/alert.gif';
                                    $resource = fopen($media, 'r');
                                    $sendphoto = InputFile::create($media, 'alert.gif');

                                    $this->telegram->setTimeOut(10)->setConnectTimeOut(10)->sendAnimation([
                                        'chat_id' => $chatId,
                                        'animation' => $sendphoto,
                                        "parse_mode" => "HTML",
                                        'caption' => $result->message,
                                    ]);

//                                    $this->telegram->setTimeOut(10)->setConnectTimeOut(10)->sendMessage([
//                                        'chat_id' => $chatId,
//                                        'text' => $result->message,
//                                    ]);

                                }

                            }

                        }

                    } elseif ($currentStep->step == 'media') {
                        $fileId = '';
                        if ($update->getMessage()->has('photo')) {
                            $photo = $message->photo[2];
                            $fileId = $photo->file_id;
                            $file = $this->telegram->setTimeOut(10)->setConnectTimeOut(10)->getFile(['file_id' => $fileId]);
                            $fileUrl = 'https://api.telegram.org/file/bot'.env('TELEGRAM_BOT_TOKEN').'/'.$file->file_path;

                            $response = Http::get($fileUrl);

                            if ($response->successful()) {

                                // รับเนื้อหาของไฟล์รูปภาพ
                                $content = $response->body();

                                // สร้างชื่อไฟล์
                                //                $filename =  uniqid() . '.jpg';
                                $filename = 'violations/'.uniqid().'.jpg';
                                // บันทึกไฟล์รูปภาพลงใน storage

                                //                Storage::putFileAs('violations',$content, $filename);
                                Storage::disk('public')->put($filename, $content);

                                $fileId = 'https://user.168csn.com/storage/'.$filename;
                            }
                            $currentStep->message = $message->getCaption();
                        } else {
                            $currentStep->message = $message->getText();
                        }

                        $currentStep->raw_numbers = $this->extractRawNumbersUnicodeSafe($currentStep->message);
                        $currentStep->media_url = $fileId;
                        $currentStep->step = 'completed';
                        $currentStep->save();

                        $this->telegram->setTimeOut(10)->setConnectTimeOut(10)->sendMessage([
                            'chat_id' => $chatId,
                            'text' => 'ขอบคุณสำหรับข้อมูล',
                        ]);

                    }
                }

            }

            return response('ok', 200);
        } catch (\Exception $e) {
            \Log::error('Webhook Error: '.$e->getMessage());

            return response('Internal Server Error', 1000);
        }
    }

    public function searchViolations($text)
    {

        try {
            return Violation::query()->whereRaw(
                'MATCH(message) AGAINST(? IN BOOLEAN MODE)',
                [$text]
            )->orWhere('raw_numbers', 'LIKE', '%"'.$text.'"%')->get();
        } catch (\Exception $exception) {
            return [];
        }

    }

    public function extractRawNumbersUnicodeSafe(string $text): array
    {
        // 1. Normalize Unicode dash เช่น ๐๘๐–๘–๐๔๘๐๙–๔ ให้กลายเป็น ASCII
        $text = str_replace(['–', '−', '‒', '—', '―'], '-', $text); // แดชทุกชนิด
        $text = preg_replace('/[^\x20-\x7E]/u', '', $text); // ตัด Unicode แปลก ๆ ออก

        // 2. ลบ @username ออก
        $text = preg_replace('/@\w+/', '', $text);

        // 3. เอาเฉพาะตัวเลข (รวมเลขที่คั่นด้วย -, ช่องว่าง)
        preg_match_all('/(?:\d[\s\-]?){9,}/', $text, $matches);

        $raws = [];

        foreach ($matches[0] as $m) {
            $onlyDigits = preg_replace('/\D/', '', $m); // เหลือแต่ตัวเลข
            if (strlen($onlyDigits) >= 9) {
                $raws[] = $onlyDigits;
            }
        }

        return array_values(array_unique($raws));
    }




}
